<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Repository\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Fight\Common\Adapter\Persistence\Doctrine\Type\AuditEntryIdDataType;
use Fight\Common\Adapter\Persistence\Doctrine\Type\MetaDataType;
use Fight\Common\Adapter\Repository\Doctrine\DoctrineAuditRepository;
use Fight\Common\Adapter\Repository\Doctrine\DoctrineNonceRepository;
use Fight\Common\Domain\Auth\Exception\NonceAlreadyConsumedException;
use Fight\Common\Domain\Auth\Nonce;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Observability\AuditEntry;
use Fight\Common\Domain\Observability\AuditEntryId;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\AbstractLogger;
use Stringable;

#[CoversNothing]
final class DoctrineMappedStorageIntegrationTest extends UnitTestCase
{
    /** @var list<Connection> */
    private array $connections = [];

    /** @var list<string> */
    private array $databasePaths = [];

    /** @var list<string> */
    private array $executedSql = [];

    /** @return iterable<string, array{?string, ?string}> */
    public static function mappings(): iterable
    {
        yield 'shipped defaults' => [null, null];
        yield 'consumer overrides' => ['consumer_nonces', 'consumer_audit'];
        yield 'nonce override only' => ['consumer_nonces', null];
        yield 'audit override only' => [null, 'consumer_audit'];
        yield 'native quoted identifiers' => ['`request-nonces`', '`audit-history`'];
    }

    #[DataProvider('mappings')]
    public function test_that_nonce_consumption_replay_and_purge_use_the_mapped_storage(
        ?string $nonceTable,
        ?string $auditTable
    ): void {
        $connection = $this->database();
        $defaultManager = $this->entityManager($connection);
        $this->install($defaultManager, Nonce::class);
        $future = new DateTimeImmutable('+1 day');
        $past = new DateTimeImmutable('-1 day');
        $defaultRows = [];
        if ($nonceTable !== null) {
            $defaultRepository = new DoctrineNonceRepository($defaultManager);
            $defaultRepository->consume(new Nonce('shared', $future));
            $defaultRepository->consume(new Nonce('expired', $past));
            $defaultRows = $connection->fetchAllAssociative('SELECT * FROM nonces ORDER BY nonce');
        }

        $entityManager = $this->entityManager($connection, $nonceTable, $auditTable);
        if ($nonceTable !== null) {
            $this->install($entityManager, Nonce::class);
        }
        $this->executedSql = [];
        $nonceRepository = new DoctrineNonceRepository($entityManager);
        $nonceRepository->consume(new Nonce('shared', $future));
        $this->assertReplayRejected($nonceRepository, new Nonce('shared', $past));
        // Expiry does not implicitly reject or release a nonce on consumption; only purge removes it.
        $nonceRepository->consume(new Nonce('expired', $past));
        $this->assertReplayRejected($nonceRepository, new Nonce('expired', $future));
        $literalValue = "value'); DELETE FROM nonces; --";
        $nonceRepository->consume(new Nonce($literalValue, $future));

        $metadata = $entityManager->getClassMetadata(Nonce::class);
        $stored = $entityManager->find(Nonce::class, 'shared');
        self::assertInstanceOf(Nonce::class, $stored);
        self::assertSame($future->format('Y-m-d H:i:s'), $stored->expiresAt()->format('Y-m-d H:i:s'));
        self::assertSame($nonceTable === null ? 'nonces' : trim($nonceTable, '`'), $metadata->getTableName());
        $nonceRepository->purgeExpired();
        $entityManager->clear();
        self::assertNull($entityManager->find(Nonce::class, 'expired'));
        self::assertInstanceOf(Nonce::class, $entityManager->find(Nonce::class, 'shared'));
        self::assertInstanceOf(Nonce::class, $entityManager->find(Nonce::class, $literalValue));
        $this->assertReplayRejected($nonceRepository, new Nonce('shared', $future));
        $nonceRepository->consume(new Nonce('expired', $future));
        if ($nonceTable !== null) {
            $this->assertNoDefaultSql('nonces');
            self::assertSame($defaultRows, $connection->fetchAllAssociative('SELECT * FROM nonces ORDER BY nonce'));
        }
    }

    #[DataProvider('mappings')]
    public function test_that_audit_flush_reopen_and_queries_use_the_mapped_storage_and_existing_types(
        ?string $nonceTable,
        ?string $auditTable
    ): void {
        $connection = $this->database();
        $databasePath = $this->databasePaths[array_key_last($this->databasePaths)];
        $defaultManager = $this->entityManager($connection);
        $this->install($defaultManager, AuditEntry::class);
        $from = new DateTimeImmutable('2026-01-02 12:00:00');
        $to = new DateTimeImmutable('2026-01-02 12:00:02');
        $defaultRows = [];
        if ($auditTable !== null) {
            (new DoctrineAuditRepository($defaultManager))->add($this->entry('2026-01-02 12:00:00'));
            $defaultManager->flush();
            // A matching default row must never be hydrated through any selected query.
            $connection->executeStatement("UPDATE audit_entries SET context = 'invalid-json'");
            $defaultRows = $connection->fetchAllAssociative('SELECT * FROM audit_entries');
        }

        $entityManager = $this->entityManager($connection, $nonceTable, $auditTable);
        if ($auditTable !== null) {
            $this->install($entityManager, AuditEntry::class);
        }
        $this->executedSql = [];
        $auditRepository = new DoctrineAuditRepository($entityManager);
        $entries = [
            $this->entry('2026-01-02 11:59:59', 'other', 'other'),
            $this->entry('2026-01-02 12:00:00'),
            $this->entry('2026-01-02 12:00:01'),
            $this->entry('2026-01-02 12:00:02'),
            $this->entry('2026-01-02 12:00:03', 'other', 'other')
        ];
        foreach ($entries as $entry) {
            $auditRepository->add($entry);
        }
        self::assertSame(0, $auditRepository->getByActor('actor', new Pagination())->totalRecords());
        $entityManager->flush();
        $entityManager->close();
        $defaultManager->close();
        $connection->close();

        // A new connection and EntityManager prove disk persistence and fresh hydration, not identity-map reuse.
        $reopenedConnection = $this->connect($databasePath);
        $reopenedManager = $this->entityManager($reopenedConnection, $nonceTable, $auditTable);
        $reopenedRepository = new DoctrineAuditRepository($reopenedManager);
        $loaded = $reopenedManager->find(AuditEntry::class, $entries[1]->id());
        self::assertInstanceOf(AuditEntry::class, $loaded);
        self::assertNotSame($entries[1], $loaded);
        self::assertInstanceOf(AuditEntryId::class, $loaded->id());
        self::assertInstanceOf(Meta::class, $loaded->context());
        self::assertSame($entries[1]->toArray(), $loaded->toArray());
        self::assertSame('audit_entry_id', $reopenedManager->getClassMetadata(AuditEntry::class)->getTypeOfField('id'));
        self::assertSame('common_meta', $reopenedManager->getClassMetadata(AuditEntry::class)->getTypeOfField('context'));
        self::assertSame('datetime_immutable', $reopenedManager->getClassMetadata(AuditEntry::class)->getTypeOfField('timestamp'));

        $actorPage = $reopenedRepository->getByActor('actor', new Pagination(2, 2, ['timestamp' => 'DESC']));
        self::assertSame(3, $actorPage->totalRecords());
        self::assertSame(2, $actorPage->page());
        self::assertSame(2, $actorPage->perPage());
        self::assertSame(1, $actorPage->count());
        self::assertSame(AuditEntry::class, $actorPage->itemType());
        self::assertSame($entries[1]->toArray(), $actorPage->records()->first()->toArray());
        $actionPage = $reopenedRepository->getByAction('action', Pagination::strict(1, 2, ['timestamp' => 'asc']));
        self::assertSame(3, $actionPage->totalRecords());
        self::assertSame(2, $actionPage->count());
        self::assertSame($entries[1]->toArray(), $actionPage->records()->first()->toArray());
        $between = $reopenedRepository->getBetween($from, $to, new Pagination(1, 10, ['timestamp' => 'ASC']));
        self::assertSame(3, $between->totalRecords());
        self::assertSame(
            array_map(static fn (AuditEntry $entry): array => $entry->toArray(), array_slice($entries, 1, 3)),
            array_map(static fn (AuditEntry $entry): array => $entry->toArray(), $between->records()->toArray())
        );
        self::assertSame(1, $reopenedRepository->getBetween($from, $from, new Pagination())->totalRecords());
        self::assertTrue($reopenedRepository->getByActor('absent', new Pagination())->isEmpty());
        self::assertTrue($reopenedRepository->getByAction('absent', new Pagination())->isEmpty());
        self::assertTrue($reopenedRepository->getBetween($to, $from, new Pagination())->isEmpty());
        if ($auditTable !== null) {
            $this->assertNoDefaultSql('audit_entries');
            self::assertSame($defaultRows, $reopenedConnection->fetchAllAssociative('SELECT * FROM audit_entries'));
        }
    }

    public function test_that_mapping_changes_do_not_create_or_transfer_storage_and_missing_nonce_storage_never_falls_back(): void
    {
        $connection = $this->database();
        $defaultManager = $this->entityManager($connection);
        $this->install($defaultManager, Nonce::class);
        $defaultRepository = new DoctrineNonceRepository($defaultManager);
        $defaultRepository->consume(new Nonce('old', new DateTimeImmutable('-1 day')));
        $before = $connection->fetchAllAssociative('SELECT * FROM nonces');
        $selectedManager = $this->entityManager($connection, 'missing_nonces');
        $selectedRepository = new DoctrineNonceRepository($selectedManager);
        self::assertSame(['nonces'], $connection->createSchemaManager()->listTableNames());
        foreach (['consume', 'purge'] as $operation) {
            try {
                if ($operation === 'consume') {
                    $selectedRepository->consume(new Nonce('new', new DateTimeImmutable('+1 day')));
                } else {
                    $selectedRepository->purgeExpired();
                }
                self::fail('Missing selected nonce storage must fail explicitly.');
            } catch (TableNotFoundException) {
                self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM nonces'));
            }
        }
        $this->install($selectedManager, Nonce::class);
        self::assertNull($selectedManager->find(Nonce::class, 'old'));
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM nonces'));
    }

    public function test_that_missing_selected_audit_queries_and_flush_never_fall_back_or_migrate(): void
    {
        $connection = $this->database();
        $defaultManager = $this->entityManager($connection);
        $this->install($defaultManager, AuditEntry::class);
        (new DoctrineAuditRepository($defaultManager))->add($this->entry('2026-01-02 12:00:00'));
        $defaultManager->flush();
        $before = $connection->fetchAllAssociative('SELECT * FROM audit_entries');
        foreach (['actor', 'action', 'between', 'flush'] as $operation) {
            $selectedManager = $this->entityManager($connection, auditTable: 'missing_audit');
            $selectedRepository = new DoctrineAuditRepository($selectedManager);
            self::assertSame(['audit_entries'], $connection->createSchemaManager()->listTableNames());
            try {
                match ($operation) {
                    'actor' => $selectedRepository->getByActor('actor', new Pagination()),
                    'action' => $selectedRepository->getByAction('action', new Pagination()),
                    'between' => $selectedRepository->getBetween(
                        new DateTimeImmutable('2026-01-01'),
                        new DateTimeImmutable('2026-01-03'),
                        new Pagination()
                    ),
                    'flush' => $selectedRepository->add($this->entry('2026-01-02 12:00:01'))
                };
                if ($operation === 'flush') {
                    $selectedManager->flush();
                }
                self::fail('Missing selected audit storage must fail explicitly.');
            } catch (TableNotFoundException) {
                self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM audit_entries'));
            }
        }
        $selectedManager = $this->entityManager($connection, auditTable: 'missing_audit');
        $this->install($selectedManager, AuditEntry::class);
        self::assertTrue((new DoctrineAuditRepository($selectedManager))->getByActor('actor', new Pagination())->isEmpty());
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM audit_entries'));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function serverMappings(): iterable
    {
        yield 'MySQL quoted table' => ['mysql', false];
        yield 'PostgreSQL quoted table' => ['postgresql', false];
        yield 'PostgreSQL native schema' => ['postgresql', true];
    }

    #[DataProvider('serverMappings')]
    #[Group('server-database')]
    public function test_that_server_nonce_sql_honors_native_quoting_and_schema(string $backend, bool $useSchema): void
    {
        $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
        self::assertIsString($dsn, 'The selected worktree disposable database is required.');
        $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        $connection = DriverManager::getConnection($params);
        $this->connections[] = $connection;
        $platform = $connection->getDatabasePlatform();
        $tableName = 'orm-nonces-'.bin2hex(random_bytes(8));
        $schemaName = $useSchema ? 'orm_schema_'.bin2hex(random_bytes(8)) : null;
        $quotedTable = $platform->quoteSingleIdentifier($tableName);
        if ($schemaName !== null) {
            $quotedTable = $platform->quoteSingleIdentifier($schemaName).'.'.$quotedTable;
        }
        try {
            $entityManager = $this->entityManager($connection, '`'.$tableName.'`', nonceSchema: $schemaName);
            $this->install($entityManager, Nonce::class);
            $nonceRepository = new DoctrineNonceRepository($entityManager);
            $nonceRepository->consume(new Nonce('retained', new DateTimeImmutable('+1 day')));
            $nonceRepository->consume(new Nonce('expired', new DateTimeImmutable('-1 day')));
            $this->assertReplayRejected($nonceRepository, new Nonce('retained', new DateTimeImmutable('+1 day')));
            $nonceRepository->purgeExpired();
            self::assertSame(['retained'], $connection->fetchFirstColumn('SELECT nonce FROM '.$quotedTable));
            self::assertInstanceOf(Nonce::class, $entityManager->find(Nonce::class, 'retained'));
        } finally {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$quotedTable);
            if ($schemaName !== null) {
                $connection->executeStatement('DROP SCHEMA IF EXISTS '.$platform->quoteSingleIdentifier($schemaName));
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
        foreach ($this->databasePaths as $path) {
            unlink($path);
        }
        parent::tearDown();
    }

    private function database(): Connection
    {
        $path = tempnam(sys_get_temp_dir(), 'fight-orm-');
        self::assertIsString($path);
        $this->databasePaths[] = $path;

        return $this->connect($path);
    }

    private function connect(string $path): Connection
    {
        $logger = new class($this->executedSql) extends AbstractLogger {
            /** @param list<string> $executedSql */
            public function __construct(private array &$executedSql)
            {
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                if (isset($context['sql'])) {
                    $this->executedSql[] = $context['sql'];
                }
            }
        };
        $configuration = new DbalConfiguration();
        $configuration->setMiddlewares([new Middleware($logger)]);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path], $configuration);
        $this->connections[] = $connection;

        return $connection;
    }

    private function entityManager(
        Connection $connection,
        ?string $nonceTable = null,
        ?string $auditTable = null,
        ?string $nonceSchema = null
    ): EntityManager {
        foreach (['audit_entry_id' => AuditEntryIdDataType::class, 'common_meta' => MetaDataType::class] as $name => $type) {
            if (!Type::hasType($name)) {
                Type::addType($name, $type);
            }
        }
        $configuration = new Configuration();
        $configuration->setMetadataDriverImpl(new SimplifiedXmlDriver([
            dirname(__DIR__, 4).'/database/schema' => 'Fight\\Common\\Domain'
        ]));
        $configuration->enableNativeLazyObjects(true);
        $entityManager = new EntityManager($connection, $configuration);
        $entityManager->getEventManager()->addEventListener([Events::loadClassMetadata], new readonly class($nonceTable, $auditTable, $nonceSchema) {
            public function __construct(
                private ?string $nonceTable,
                private ?string $auditTable,
                private ?string $nonceSchema
            ) {
            }

            public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
            {
                $metadata = $event->getClassMetadata();
                $table = match ($metadata->getName()) {
                    Nonce::class => $this->nonceTable,
                    AuditEntry::class => $this->auditTable,
                    default => null
                };
                if ($table !== null) {
                    $mapping = ['name' => $table];
                    if ($metadata->getName() === Nonce::class && $this->nonceSchema !== null) {
                        $mapping['schema'] = $this->nonceSchema;
                    }
                    $metadata->setPrimaryTable($mapping);
                }
            }
        });

        return $entityManager;
    }

    /** @param class-string $entityClass */
    private function install(EntityManager $entityManager, string $entityClass): void
    {
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata($entityClass)]);
    }

    private function assertReplayRejected(DoctrineNonceRepository $nonceRepository, Nonce $nonce): void
    {
        try {
            $nonceRepository->consume($nonce);
            self::fail('Duplicate nonce must retain the existing exception family.');
        } catch (NonceAlreadyConsumedException $nonceAlreadyConsumedException) {
            self::assertSame('Nonce already consumed', $nonceAlreadyConsumedException->getMessage());
        }
    }

    private function assertNoDefaultSql(string $defaultTable): void
    {
        self::assertNotEmpty($this->executedSql);
        foreach ($this->executedSql as $sql) {
            self::assertDoesNotMatchRegularExpression(
                '/\\b(?:FROM|INTO|UPDATE|JOIN)\\s+["`]?'.preg_quote($defaultTable, '/').'["`]?\\b/i',
                $sql
            );
        }
    }

    private function entry(string $timestamp, string $actor = 'actor', string $action = 'action'): AuditEntry
    {
        return new AuditEntry(
            AuditEntryId::generate(),
            $actor,
            $action,
            new DateTimeImmutable($timestamp),
            Meta::create(['nested' => ['label' => 'retained', 'count' => 42], 'enabled' => true])
        );
    }
}
