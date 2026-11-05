<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Detail\Dbal\DbalProtectedContentRepository;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationTables;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Mcp\Persistence\Observation\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Mcp\Persistence\Phase\Dbal\DbalPhaseRepository;
use Fight\Common\Adapter\Mcp\Persistence\Session\Dbal\DbalSessionRepository;
use Fight\Common\Application\Mcp\Observation\Exception\ObservationStorageFailed;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Phase\PhaseId;
use Fight\Common\Domain\Mcp\Session\SessionId;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\Mcp\Execution\RecordingFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversNothing]
final class DbalConfiguredRecordingIntegrationTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('selections')]
    public function test_that_independent_overrides_install_and_reopen_without_changing_omitted_defaults(
        string $backend,
        array $overrides
    ): void {
        $connection = $this->database($backend);
        $tables = new DbalObservationTables(...$overrides);
        $schema = new DbalObservationSchema(tables: $tables);
        self::assertSame(0, $this->executionTableCount($connection));
        $connection->executeStatement('CREATE TABLE consumer_owned_recording (marker INTEGER)');
        $connection->insert('consumer_owned_recording', ['marker' => 42]);
        $schema->install($connection);
        $sentinels = [];
        foreach ($this->names($tables) as $index => $name) {
            $default = DbalObservationSchema::TABLES[$index];
            if ($name !== $default) {
                // Populated overridden counterparts must never participate, even with incompatible shape.
                $connection->executeStatement('CREATE TABLE '.$default.' (marker INTEGER)');
                $connection->insert($default, ['marker' => 42]);
                $sentinels[] = $default;
            }
        }
        $transaction = new DbalRecordingTransaction(connection: $connection, tables: $tables);
        self::assertSame($tables, $transaction->tables());
        $fixture = new RecordingFixture($transaction);
        foreach ([ObservationFixture::session(), ObservationFixture::phase(), ObservationFixture::activity()] as $observation) {
            $fixture->record($observation);
        }
        $detail = ObservationFixture::detail();
        $fixture->record($detail, ProtectedContent::create($detail->detail(), 'selected-private'));
        $reopened = new DbalRecordingTransaction($this->connect($connection->getParams()), $tables);
        self::assertSame('build:147', (new DbalSessionRepository($reopened))->getById(SessionId::fromStrings('project', 'session'))->consumerReference());
        self::assertSame('agent', (new DbalPhaseRepository($reopened))->getById(PhaseId::fromStrings('project', 'phase'))->agentId());
        self::assertSame($detail->toArray(), (new DbalObservationRepository($reopened))->getById('instrumentation', $detail->id())->toArray());
        self::assertSame('selected-private', (new DbalProtectedContentRepository($reopened))->getFor($detail)->body());
        $before = $this->snapshot($connection, $tables);
        $schema->install($connection);
        self::assertSame($before, $this->snapshot($connection, $tables));
        self::assertSame(42, (int) $connection->fetchOne('SELECT marker FROM consumer_owned_recording'));
        foreach ($sentinels as $default) {
            self::assertSame([['marker' => 42]], $connection->fetchAllAssociative('SELECT marker FROM '.$default));
        }
        $existing = array_map(static fn (string $name): string => trim($name, '`"'), $connection->createSchemaManager()->listTableNames());
        foreach ($this->names($tables) as $index => $name) {
            self::assertContains($name, $existing);
            self::assertContains(DbalObservationSchema::TABLES[$index], $existing);
        }
    }

    #[DataProvider('backends')]
    public function test_that_populated_defaults_are_neither_fallback_storage_nor_migrated_by_a_new_selection(string $backend): void
    {
        $connection = $this->database($backend);
        $defaults = new DbalObservationTables();
        (new DbalObservationSchema())->install($connection);
        $defaultTransaction = new DbalRecordingTransaction($connection);
        $defaultFixture = new RecordingFixture($defaultTransaction);
        foreach ([ObservationFixture::session(), ObservationFixture::phase(), ObservationFixture::activity()] as $observation) {
            $defaultFixture->record($observation);
        }
        $detail = ObservationFixture::detail();
        $defaultFixture->record($detail, ProtectedContent::create($detail->detail(), 'default-private'));
        // An overridden state table must not read a valid default or lock it as a substitute.
        $connection->update('fight_execution_schema', ['version' => 2], ['singleton' => 1]);
        $before = $this->snapshot($connection, $defaults);
        $tables = new DbalObservationTables('Current_user', 'ExecutionSessions', 'ExecutionPhases', 'ExecutionObservations', 'ExecutionContents');
        $transaction = new DbalRecordingTransaction($connection, $tables);
        $fixture = new RecordingFixture($transaction);
        try {
            $fixture->record(ObservationFixture::session());
            self::fail('Missing selected storage must not install or fall back.');
        } catch (ObservationStorageFailed $exception) {
            self::assertFalse($exception->outcomeUncertain());
        }
        try {
            (new DbalSessionRepository($transaction))->getById(SessionId::fromStrings('project', 'session'));
            self::fail('A repository must not fall back to populated defaults.');
        } catch (Exception) {
            self::assertSame($before, $this->snapshot($connection, $defaults));
        }
        self::assertSame(5, $this->executionTableCount($connection));
        (new DbalObservationSchema($tables))->install($connection);
        self::assertNull((new DbalSessionRepository($transaction))->getById(SessionId::fromStrings('project', 'session')));
        self::assertNull((new DbalPhaseRepository($transaction))->getById(PhaseId::fromStrings('project', 'phase')));
        self::assertNull((new DbalObservationRepository($transaction))->getById('instrumentation', $detail->id()));
        self::assertNull((new DbalProtectedContentRepository($transaction))->getFor($detail));
        foreach ([ObservationFixture::session(['outcome' => 'succeeded']), ObservationFixture::phase(), ObservationFixture::activity()] as $observation) {
            $fixture->record($observation);
        }
        $fixture->record($detail, ProtectedContent::create($detail->detail(), 'selected-private'));
        $fixture->record($detail, ProtectedContent::create($detail->detail(), 'selected-private'));
        $reopened = new DbalRecordingTransaction($this->connect($connection->getParams()), $tables);
        self::assertSame('selected-private', (new DbalProtectedContentRepository($reopened))->getFor($detail)->body());
        self::assertSame('succeeded', (new DbalObservationRepository($reopened))->getById('instrumentation', ObservationFixture::session()->id())->outcome()->value);
        $transaction->commitTransactional(static fn () => (new DbalProtectedContentRepository($transaction))->removeFor($detail));
        self::assertNull((new DbalProtectedContentRepository($reopened))->getFor($detail));
        (new DbalObservationSchema($tables))->install($connection);
        self::assertSame($before, $this->snapshot($connection, $defaults));
        self::assertSame('default-private', (new DbalProtectedContentRepository($defaultTransaction))->getFor($detail)->body());
        self::assertSame(10, $this->executionTableCount($connection));
    }

    #[DataProvider('backends')]
    public function test_that_selected_partial_versions_and_missing_indexes_reject_without_repair(string $backend): void
    {
        $connection = $this->database($backend);
        $tables = new DbalObservationTables('Current_user', 'ExecutionSessions', 'ExecutionPhases', 'ExecutionObservations', 'ExecutionContents');
        $schema = new DbalObservationSchema($tables);
        $schema->install($connection);
        $state = $tables->schemaTable()->quoted($connection->getDatabasePlatform());
        $connection->update($state, ['version' => 2], ['singleton' => 1]);
        try {
            $schema->install($connection);
            self::fail('Incompatible selected state must reject.');
        } catch (RuntimeException $exception) {
            self::assertSame('Unsupported or incomplete observation schema.', $exception->getMessage());
        }
        $transaction = new DbalRecordingTransaction($connection, $tables);
        try {
            $transaction->commitTransactional(static function (): void { self::fail('Unsupported state cannot admit recording.'); });
            self::fail('Selected runtime version must reject.');
        } catch (RuntimeException $exception) {
            self::assertSame('Unsupported observation storage version.', $exception->getMessage());
        }
        self::assertSame(2, (int) $connection->fetchOne('SELECT version FROM '.$state.' WHERE singleton = 1'));
        $connection->update($state, ['version' => 1], ['singleton' => 1]);
        $manager = $connection->createSchemaManager();
        foreach ($manager->introspectTable($tables->observationTable()->schemaName())->getIndexes() as $index) {
            if ($index->getColumns() === ['recorded_at', 'observation_id']) {
                $manager->dropIndex($index->getName(), $tables->observationTable()->schemaName());
            }
        }
        try {
            $schema->install($connection);
            self::fail('Missing selected recording index must reject, not repair.');
        } catch (RuntimeException $exception) {
            self::assertSame('Incomplete observation schema version one.', $exception->getMessage());
        }
        $manager->dropTable($tables->contentTable()->quoted($connection->getDatabasePlatform()));
        try {
            $schema->install($connection);
            self::fail('Partial selected storage must reject, not reinstall.');
        } catch (RuntimeException $exception) {
            self::assertSame('Unsupported or incomplete observation schema.', $exception->getMessage());
        }
        self::assertSame(4, $this->executionTableCount($connection));
        self::assertFalse($connection->isTransactionActive());
    }

    public static function backends(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend => [$backend];
        }
    }

    public static function selections(): iterable
    {
        $all = ['schemaTable' => 'Current_user', 'sessionTable' => 'ExecutionSessions', 'phaseTable' => 'ExecutionPhases', 'observationTable' => 'ExecutionObservations', 'contentTable' => 'ExecutionContents'];
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend.'-default' => [$backend, []];
            foreach ($all as $key => $name) {
                yield $backend.'-'.$key => [$backend, [$key => $name]];
            }
            yield $backend.'-all' => [$backend, $all];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
        foreach ($this->directories as $directory) {
            $this->removeTemporaryDirectory($directory, 'fight-configured-execution-');
        }
        parent::tearDown();
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') {
            $directory = sys_get_temp_dir().'/fight-configured-execution-'.bin2hex(random_bytes(8));
            self::assertTrue(mkdir($directory, 0700));
            $this->directories[] = $directory;
            $params = ['driver' => 'pdo_sqlite', 'path' => $directory.'/observations.sqlite'];
        } else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Native server DSN required; skipped cases do not qualify storage.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = $this->connect($params);
        $platform = $connection->getDatabasePlatform();
        $configured = ['Current_user', 'ExecutionSessions', 'ExecutionPhases', 'ExecutionObservations', 'ExecutionContents'];
        // Partial selections can link a default child to an overridden parent, and vice versa.
        foreach (array_reverse(array_keys($configured)) as $index) {
            foreach ([$configured[$index], DbalObservationSchema::TABLES[$index]] as $name) {
                $connection->executeStatement('DROP TABLE IF EXISTS '.$platform->quoteSingleIdentifier($name));
            }
        }
        $connection->executeStatement('DROP TABLE IF EXISTS consumer_owned_recording');
        return $connection;
    }

    private function connect(array $params): Connection
    {
        $connection = DriverManager::getConnection($params);
        if ($params['driver'] === 'pdo_sqlite') {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }
        $this->connections[] = $connection;
        return $connection;
    }

    private function names(DbalObservationTables $tables): array
    {
        return array_map(static fn ($table): string => $table->name(), [$tables->schemaTable(), $tables->sessionTable(), $tables->phaseTable(), $tables->observationTable(), $tables->contentTable()]);
    }

    private function executionTableCount(Connection $connection): int
    {
        $existing = array_map(static fn (string $name): string => trim($name, '`"'), $connection->createSchemaManager()->listTableNames());
        return count(array_intersect($existing, [...DbalObservationSchema::TABLES, 'Current_user', 'ExecutionSessions', 'ExecutionPhases', 'ExecutionObservations', 'ExecutionContents']));
    }

    private function snapshot(Connection $connection, DbalObservationTables $tables): array
    {
        $rows = [];
        foreach ($this->names($tables) as $name) {
            $rows[$name] = $connection->fetchAllAssociative('SELECT * FROM '.$connection->getDatabasePlatform()->quoteSingleIdentifier($name));
        }
        return $rows;
    }
}
