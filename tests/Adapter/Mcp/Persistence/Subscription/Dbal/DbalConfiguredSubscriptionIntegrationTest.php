<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Subscription\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriberReader;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionSchema;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionStore;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionTables;
use Fight\Common\Application\Mcp\Subscription\Exception\SubscriberReadFailed;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberAccess;
use Fight\Common\Domain\Mcp\Subscription\Query\ListSubscribers;
use Fight\Common\Domain\Mcp\Subscription\SubscriberSelection;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

#[CoversNothing]
final class DbalConfiguredSubscriptionIntegrationTest extends UnitTestCase
{
    private array $connections = [];
    private array $databases = [];
    private array $sqlitePaths = [];

    #[DataProvider('selections')]
    public function test_that_each_selection_owns_registration_capacity_renewal_closure_and_expiry(string $backend, array $selection): void
    {
        $connection = $this->database($backend);
        $tables = new DbalSubscriptionTables(...$selection);
        $schema = new DbalSubscriptionSchema(tables: $tables);
        $beforeInstall = $this->tableNames($connection);
        $schema->install($connection);
        $schema->install($connection);
        $created = array_values(array_diff($this->tableNames($connection), $beforeInstall));
        $expected = [$tables->stateTable()->name(), $tables->subscriptionTable()->name()];
        sort($created);
        sort($expected);
        self::assertSame($expected, $created, 'Installation creates only the two selected counterparts.');
        $this->installDefaultCounterparts($connection);
        $this->poisonOverriddenDefaults($connection, $tables);
        $defaultSnapshot = $this->defaultSnapshot($connection, $tables);
        $registrationName = $tables->subscriptionTable()->quoted($connection->getDatabasePlatform());
        $subscriptionStore = new DbalSubscriptionStore(connection: $connection, maxActive: 2, tables: $tables);
        $readerConnection = $this->connect($connection->getParams());
        $reader = new DbalSubscriberReader(connection: $readerConnection, storageScope: 'db', clock: static fn () => 103, tables: $tables);
        $selection = SubscriberSelection::create(['scope']);
        $access = SubscriberAccess::create(['scope']);
        self::assertSame(0, $reader->summarize($selection, $access)->toArray()['subscriptions']);
        $connection->beginTransaction();
        self::assertInstanceOf(LogicException::class, $this->fails(fn () => $subscriptionStore->cleanup(100, 1)));
        self::assertTrue($connection->isTransactionActive());
        $connection->rollBack();

        $subscriptionStore->open(self::id(1), self::id(90), self::context(), 100, 105);
        $subscriptionStore->open(self::id(2), self::id(91), self::context('second'), 100, 105);
        self::assertSame(2, $reader->summarize($selection, $access)->toArray()['subscriptions']);
        $capacityFailure = $this->fails(fn () => $subscriptionStore->open(self::id(3), self::id(90), self::context(), 100, 105));
        self::assertInstanceOf(RuntimeException::class, $capacityFailure);
        self::assertSame('Subscription capacity exhausted.', $capacityFailure->getMessage());
        self::assertInstanceOf(LogicException::class, $this->fails(fn () => $subscriptionStore->cleanup(100, 1)));
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$registrationName));
        self::assertFalse($connection->isTransactionActive());

        $subscriptionStore = new DbalSubscriptionStore(connection: $connection, maxActive: 2, tables: $tables);
        $beforeWrongOwner = $connection->fetchAllAssociative('SELECT * FROM '.$registrationName.' ORDER BY registration_id');
        $subscriptionStore->close(self::id(1), self::id(91), 101);
        self::assertSame($beforeWrongOwner, $connection->fetchAllAssociative('SELECT * FROM '.$registrationName.' ORDER BY registration_id'));
        self::assertInstanceOf(RuntimeException::class, $this->fails(fn () => $subscriptionStore->heartbeat(self::id(1), self::id(91), 101, 106)));
        self::assertSame($beforeWrongOwner, $connection->fetchAllAssociative('SELECT * FROM '.$registrationName.' ORDER BY registration_id'));

        $subscriptionStore = new DbalSubscriptionStore(connection: $connection, maxActive: 2, tables: $tables);
        $subscriptionStore->heartbeat(self::id(1), self::id(90), 101, 106);
        $subscriptionStore->heartbeat(self::id(1), self::id(90), 101, 106);
        $renewed = $connection->fetchAssociative('SELECT observed_at, expires_at FROM '.$registrationName.' WHERE registration_id = ?', [self::id(1)]);
        self::assertSame(101, (int) $renewed['observed_at']);
        self::assertSame(106, (int) $renewed['expires_at']);
        $subscriptionStore->close(self::id(1), self::id(90), 102);
        $subscriptionStore->close(self::id(1), self::id(90), 103);
        self::assertSame(102, (int) $connection->fetchOne('SELECT closed_at FROM '.$registrationName.' WHERE registration_id = ?', [self::id(1)]));
        self::assertSame(1, $reader->summarize(SubscriberSelection::create(['scope'], ['state' => 'disconnected']), $access)->toArray()['subscriptions']);
        self::assertInstanceOf(RuntimeException::class, $this->fails(fn () => $subscriptionStore->heartbeat(self::id(1), self::id(90), 103, 108)));
        $subscriptionStore = new DbalSubscriptionStore(connection: $connection, maxActive: 2, tables: $tables);
        self::assertSame(1, $subscriptionStore->cleanup(103, 1));
        self::assertSame(0, $subscriptionStore->cleanup(103, 1));
        self::assertSame(1, $reader->summarize($selection, $access)->toArray()['subscriptions']);
        self::assertInstanceOf(RuntimeException::class, $this->fails(fn () => $subscriptionStore->heartbeat(self::id(2), self::id(91), 105, 110)));
        $expiredReader = new DbalSubscriberReader(connection: $readerConnection, storageScope: 'db', clock: static fn () => 105, tables: $tables);
        self::assertSame(1, $expiredReader->summarize(SubscriberSelection::create(['scope'], ['state' => 'stale_uncertain']), $access)->toArray()['subscriptions']);
        $subscriptionStore = new DbalSubscriptionStore(connection: $connection, maxActive: 2, tables: $tables);
        self::assertSame(1, $subscriptionStore->cleanup(105, 1));
        self::assertSame(0, $subscriptionStore->cleanup(105, 256));
        $subscriptionStore->open(self::id(1), self::id(91), self::context('replacement'), 105, 110);
        self::assertInstanceOf(RuntimeException::class, $this->fails(fn () => $subscriptionStore->heartbeat(self::id(1), self::id(90), 106, 111)));
        $replacementReader = new DbalSubscriberReader(connection: $readerConnection, storageScope: 'db', clock: static fn () => 106, tables: $tables);
        self::assertSame('replacement', $replacementReader->list(new ListSubscribers($selection), $access)->subscribers()[0]->subscriber());
        self::assertSame($defaultSnapshot, $this->defaultSnapshot($connection, $tables));
        self::assertFalse($connection->isTransactionActive());
        self::assertFalse($readerConnection->isTransactionActive());
    }

    #[DataProvider('databases')]
    public function test_that_selected_reader_batches_concealment_and_committed_boundaries_use_independent_connections(string $backend): void
    {
        $connection = $this->database($backend);
        $tables = new DbalSubscriptionTables('Join', 'Union');
        (new DbalSubscriptionSchema(tables: $tables))->install($connection);
        $this->installDefaultCounterparts($connection);
        $this->poisonOverriddenDefaults($connection, $tables);
        $defaultSnapshot = $this->defaultSnapshot($connection, $tables);
        $subscriptionStore = new DbalSubscriptionStore(connection: $connection, tables: $tables);
        for ($index = 1; $index <= 70; ++$index) {
            $subscriptionStore->open(self::id($index), self::id(90), self::context('agent-'.($index % 2)), 100, 105);
        }

        $subscriptionStore->open(self::id(71), self::id(90), self::context('CONCEALED', ['concealed']), 100, 105);
        $subscriptionStore->open(self::id(72), self::id(90), self::context('CONCEALED-MIX', ['scope', 'concealed']), 100, 105);
        $subscriptionStore->open(self::id(73), self::id(90), self::context('CONCEALED-EMPTY', []), 100, 105);
        $readerConnection = $this->connect($connection->getParams());
        $reader = new DbalSubscriberReader(connection: $readerConnection, storageScope: 'db', clock: static fn () => 103, tables: $tables);
        $selection = SubscriberSelection::create(['scope', 'concealed']);
        $access = SubscriberAccess::create(['scope']);
        $registrationName = $tables->subscriptionTable()->quoted($connection->getDatabasePlatform());
        $stateName = $tables->stateTable()->quoted($connection->getDatabasePlatform());
        $beforeReads = $connection->fetchAllAssociative('SELECT * FROM '.$registrationName.' ORDER BY registration_id');
        $summary = $reader->summarize($selection, $access)->toArray();
        self::assertSame(70, $summary['subscriptions']);
        self::assertSame(2, $summary['subscriber_ids']);
        self::assertSame(['recently_observed' => 70, 'disconnected' => 0, 'stale_uncertain' => 0], $summary['states']);
        $page = $reader->list(new ListSubscribers($selection, 2, 16), $access)->toArray();
        self::assertSame(70, $page['total']);
        self::assertCount(16, $page['subscribers']);
        self::assertSame(array_map(self::id(...), range(54, 39)), array_column($page['subscribers'], 'registration_id'));
        self::assertStringNotContainsString('CONCEALED', json_encode($page, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('owner', json_encode($page, JSON_THROW_ON_ERROR));
        $ascending = $reader->list(new ListSubscribers($selection, 5, 16, ['opened_at' => 'ASC']), $access)->toArray();
        self::assertSame(array_map(self::id(...), range(65, 70)), array_column($ascending['subscribers'], 'registration_id'));
        self::assertSame([], $reader->list(new ListSubscribers($selection, 100, 16), $access)->toArray()['subscribers']);
        self::assertSame(1, $reader->summarize($selection, SubscriberAccess::create(['scope'], [self::id(1)]))->toArray()['subscriptions']);
        self::assertSame(35, $reader->summarize(SubscriberSelection::create(['scope'], ['subscriber' => 'agent-1']), $access)->toArray()['subscriptions']);
        self::assertSame($beforeReads, $connection->fetchAllAssociative('SELECT * FROM '.$registrationName.' ORDER BY registration_id'));

        $connection->beginTransaction();
        $connection->insert($registrationName, self::row(74));
        $connection->update($stateName, ['version' => 77], ['singleton' => 1]);
        $connection->update($registrationName, ['closed_at' => 102, 'observed_at' => 102], ['registration_id' => self::id(1)]);
        self::assertSame(70, $reader->summarize($selection, $access)->toArray()['subscriptions']);
        self::assertSame(0, $reader->summarize(SubscriberSelection::create(['scope'], ['state' => 'disconnected']), $access)->toArray()['subscriptions']);
        $connection->rollBack();
        self::assertSame($beforeReads, $connection->fetchAllAssociative('SELECT * FROM '.$registrationName.' ORDER BY registration_id'));
        $connection->beginTransaction();
        $connection->insert($registrationName, self::row(74));
        $connection->commit();
        self::assertSame(71, $reader->summarize($selection, $access)->toArray()['subscriptions']);
        $connection->update($stateName, ['version' => 77], ['singleton' => 1]);
        self::assertInstanceOf(SubscriberReadFailed::class, $this->fails(fn () => $reader->summarize($selection, $access)));
        self::assertInstanceOf(SubscriberReadFailed::class, $this->fails(fn () => $reader->list(new ListSubscribers($selection), $access)));
        $connection->update($stateName, ['version' => 1], ['singleton' => 1]);

        $readerConnection->beginTransaction();
        self::assertInstanceOf(SubscriberReadFailed::class, $this->fails(fn () => $reader->summarize($selection, $access)));
        self::assertInstanceOf(SubscriberReadFailed::class, $this->fails(fn () => $reader->list(new ListSubscribers($selection), $access)));
        self::assertTrue($readerConnection->isTransactionActive());
        $readerConnection->rollBack();
        $isolation = $readerConnection->getTransactionIsolation();
        $readerConnection->setTransactionIsolation(TransactionIsolationLevel::READ_UNCOMMITTED);
        self::assertInstanceOf(SubscriberReadFailed::class, $this->fails(fn () => $reader->summarize($selection, $access)));
        $readerConnection->setTransactionIsolation($isolation);
        self::assertSame(71, $reader->summarize($selection, $access)->toArray()['subscriptions']);
        self::assertSame($defaultSnapshot, $this->defaultSnapshot($connection, $tables));
        self::assertFalse($connection->isTransactionActive());
        self::assertFalse($readerConnection->isTransactionActive());
    }

    #[DataProvider('databases')]
    public function test_that_total_capacity_and_bounded_cleanup_are_scoped_to_selected_registration_storage(string $backend): void
    {
        $connection = $this->database($backend);
        $tables = new DbalSubscriptionTables('Join', 'Union');
        (new DbalSubscriptionSchema(tables: $tables))->install($connection);
        $this->installDefaultCounterparts($connection);
        $this->poisonOverriddenDefaults($connection, $tables);
        $defaultSnapshot = $this->defaultSnapshot($connection, $tables);
        $registrationName = $tables->subscriptionTable()->quoted($connection->getDatabasePlatform());
        $this->fillCapacity($connection, $registrationName, false);
        $subscriptionStore = new DbalSubscriptionStore(connection: $connection, tables: $tables);
        $failure = $this->fails(fn () => $subscriptionStore->open(self::id(5000), self::id(90), self::context(), 100, 105));
        self::assertSame('Subscription capacity exhausted.', $failure->getMessage());
        self::assertSame(4096, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$registrationName));
        $subscriptionStore = new DbalSubscriptionStore(connection: $connection, tables: $tables);
        self::assertSame(256, $subscriptionStore->cleanup(100, 256));
        $subscriptionStore->open(self::id(5000), self::id(90), self::context(), 100, 105);
        self::assertSame(3841, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$registrationName));
        self::assertSame($defaultSnapshot, $this->defaultSnapshot($connection, $tables));
    }

    #[DataProvider('storageFailures')]
    public function test_that_missing_partial_and_incompatible_storage_never_falls_back_installs_or_migrates(string $backend, string $failureMode): void
    {
        $connection = $this->database($backend);
        (new DbalSubscriptionSchema())->install($connection);
        (new DbalSubscriptionStore(connection: $connection))->open(self::id(1), self::id(90), self::context('default-only'), 100, 105);
        $defaults = $connection->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions');
        $tables = new DbalSubscriptionTables('Join', 'Union');
        $schema = new DbalSubscriptionSchema(tables: $tables);
        $platform = $connection->getDatabasePlatform();
        $stateName = $tables->stateTable()->quoted($platform);
        $registrationName = $tables->subscriptionTable()->quoted($platform);
        if ($failureMode === 'state-only' || $failureMode === 'registration-only') {
            $definition = $connection->createSchemaManager()->introspectSchema();
            $schema->addTo($definition, $platform);
            $onlyTable = $failureMode === 'state-only' ? $tables->stateTable() : $tables->subscriptionTable();
            $connection->createSchemaManager()->createTable($definition->getTable($onlyTable->schemaName()));
            if ($failureMode === 'state-only') {
                $connection->insert($stateName, ['singleton' => 1, 'version' => 1]);
            }
        } elseif ($failureMode !== 'missing') {
            $schema->install($connection);
            if ($failureMode === 'version') {
                $connection->update($stateName, ['version' => 77], ['singleton' => 1]);
            } elseif ($failureMode === 'singleton') {
                $connection->delete($stateName, ['singleton' => 1]);
            } else {
                $connection->executeStatement('ALTER TABLE '.$registrationName.' DROP COLUMN context');
            }
        }

        $beforeTables = $connection->createSchemaManager()->listTableNames();
        foreach ([
            fn () => (new DbalSubscriptionStore(connection: $connection, tables: $tables))->open(self::id(2), self::id(90), self::context(), 100, 105),
            fn () => (new DbalSubscriptionStore(connection: $connection, tables: $tables))->heartbeat(self::id(1), self::id(90), 101, 106)
        ] as $operation) {
            $this->fails($operation);
            self::assertFalse($connection->isTransactionActive());
        }

        // Cleanup and closure do not require context, but must reject absent state or registration storage.
        if ($failureMode !== 'context-column') {
            $this->fails(fn () => (new DbalSubscriptionStore(connection: $connection, tables: $tables))->cleanup(103, 1));
            $this->fails(fn () => (new DbalSubscriptionStore(connection: $connection, tables: $tables))->close(self::id(1), self::id(90), 103));
        }

        $reader = new DbalSubscriberReader(connection: $this->connect($connection->getParams()), storageScope: 'db', clock: static fn () => 103, tables: $tables);
        $selection = SubscriberSelection::create(['scope']);
        $access = SubscriberAccess::create(['scope']);
        foreach ([fn () => $reader->summarize($selection, $access), fn () => $reader->list(new ListSubscribers($selection), $access)] as $operation) {
            $failure = $this->fails($operation);
            self::assertInstanceOf(SubscriberReadFailed::class, $failure);
            self::assertNotNull($failure->getPrevious());
        }

        self::assertSame(0, $reader->summarize($selection, SubscriberAccess::create([]))->toArray()['subscriptions']);
        self::assertSame(0, $reader->summarize(SubscriberSelection::create([]), $access)->toArray()['subscriptions']);
        self::assertSame($beforeTables, $connection->createSchemaManager()->listTableNames(), 'Runtime never installs selected storage.');
        self::assertSame($defaults, $connection->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT version FROM fight_mcp_subscription_state'));
        if ($failureMode !== 'missing' && $failureMode !== 'context-column') {
            $this->fails(fn () => $schema->install($connection));
            self::assertSame($beforeTables, $connection->createSchemaManager()->listTableNames(), 'Installer rejects partial or incompatible selections without repairing them.');
        } elseif ($failureMode === 'missing') {
            $schema->install($connection);
            self::assertSame(0, $reader->summarize($selection, $access)->toArray()['subscriptions'], 'Explicit installation does not copy populated defaults.');
        }

        self::assertSame($defaults, $connection->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions'));
    }

    #[DataProvider('databases')]
    public function test_that_selected_expiry_index_is_validated_without_repair_or_default_index_fallback(string $backend): void
    {
        $connection = $this->database($backend);
        (new DbalSubscriptionSchema())->install($connection);
        $tables = new DbalSubscriptionTables('Join', 'Union');
        $schema = new DbalSubscriptionSchema(tables: $tables);
        $schema->install($connection);
        $platform = $connection->getDatabasePlatform();
        $index = 'mcp_subscription_expiry_'.substr(hash('sha256', 'Union'), 0, 16);
        $quotedIndex = $platform->quoteSingleIdentifier($index);
        $sql = 'DROP INDEX '.$quotedIndex;
        if ($backend === 'mysql') {
            $sql .= ' ON '.$tables->subscriptionTable()->quoted($platform);
        }

        $connection->executeStatement($sql);
        self::assertInstanceOf(RuntimeException::class, $this->fails(fn () => $schema->install($connection)));
        self::assertFalse($connection->createSchemaManager()->introspectTable($tables->subscriptionTable()->schemaName())->hasIndex($index));
        self::assertTrue($connection->createSchemaManager()->introspectTable('fight_mcp_subscriptions')->hasIndex('fight_mcp_subscription_expiry'));
    }

    public static function selections(): iterable
    {
        foreach (self::databases() as $backend => [$name]) {
            foreach ([
                'defaults' => [],
                'state-only' => ['stateTable' => 'Join'],
                'registration-only' => ['subscriptionTable' => 'Union'],
                'both' => ['stateTable' => 'Join', 'subscriptionTable' => 'Union']
            ] as $mode => $selection) {
                yield $backend.'-'.$mode => [$name, $selection];
            }
        }
    }

    public static function storageFailures(): iterable
    {
        foreach (self::databases() as $backend => [$name]) {
            foreach (['missing', 'state-only', 'registration-only', 'version', 'singleton', 'context-column'] as $mode) {
                yield $backend.'-'.$mode => [$name, $mode];
            }
        }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend => [$backend];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        foreach ($this->databases as $connection) {
            $this->dropOwnedTables($connection);
        }

        foreach ($this->connections as $connection) {
            $connection->close();
        }

        foreach ($this->sqlitePaths as $path) {
            unlink($path);
        }

        parent::tearDown();
    }

    private static function id(int $value): string
    {
        return str_pad(dechex($value), 32, '0', STR_PAD_LEFT);
    }

    private static function context(string $subscriber = 'agent', array $scopes = ['scope']): array
    {
        return [
            'subscriber' => $subscriber,
            'selection' => ['scopes' => $scopes, 'filters' => ['topic' => 'topic']],
            'requested' => ['toolsListChanged' => true],
            'accepted' => ['toolsListChanged' => true]
        ];
    }

    private static function row(int $identity): array
    {
        return [
            'registration_id' => self::id($identity), 'owner' => self::id(90),
            'context' => json_encode(self::context(), JSON_THROW_ON_ERROR),
            'opened_at' => 100, 'observed_at' => 100, 'expires_at' => 105, 'closed_at' => 0
        ];
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') {
            $path = tempnam(sys_get_temp_dir(), 'subscription-tables-');
            self::assertIsString($path);
            $this->sqlitePaths[] = $path;
            $params = ['driver' => 'pdo_sqlite', 'path' => $path];
        } else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual disposable server database required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }

        $connection = $this->connect($params);
        $this->databases[] = $connection;
        $this->dropOwnedTables($connection);

        return $connection;
    }

    private function connect(array $params): Connection
    {
        $connection = DriverManager::getConnection($params);
        $this->connections[] = $connection;

        return $connection;
    }

    private function tableNames(Connection $connection): array
    {
        return array_map(static fn (string $name): string => trim($name, '`"'), $connection->createSchemaManager()->listTableNames());
    }

    private function dropOwnedTables(Connection $connection): void
    {
        // These are the exact fixture tables; never enumerate/drop unrelated Task, Notice or execution storage.
        $platform = $connection->getDatabasePlatform();
        foreach (['Union', 'Join', ...array_reverse(DbalSubscriptionSchema::TABLES)] as $name) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$platform->quoteSingleIdentifier($name));
        }
    }

    private function installDefaultCounterparts(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $templateConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $definition = $templateConnection->createSchemaManager()->introspectSchema();
            (new DbalSubscriptionSchema())->addTo($definition, $connection->getDatabasePlatform());
            $present = $schemaManager->listTableNames();
            foreach ([(new DbalSubscriptionTables())->stateTable(), (new DbalSubscriptionTables())->subscriptionTable()] as $table) {
                if (in_array($table->name(), $present, true)) {
                    continue;
                }

                $schemaManager->createTable($definition->getTable($table->schemaName()));
                if ($table->name() === 'fight_mcp_subscription_state') {
                    $connection->insert($table->quoted($connection->getDatabasePlatform()), ['singleton' => 1, 'version' => 1]);
                }
            }
        } finally {
            $templateConnection->close();
        }
    }

    private function poisonOverriddenDefaults(Connection $connection, DbalSubscriptionTables $tables): void
    {
        if ($tables->stateTable()->name() !== 'fight_mcp_subscription_state') {
            $connection->update('fight_mcp_subscription_state', ['version' => 77], ['singleton' => 1]);
        }

        if ($tables->subscriptionTable()->name() !== 'fight_mcp_subscriptions') {
            // Both the active and total capacity counters, and any accidental reader scan, are poisoned.
            $this->fillCapacity($connection, 'fight_mcp_subscriptions', true);
        }
    }

    private function fillCapacity(Connection $connection, string $quotedTable, bool $poison): void
    {
        $connection->beginTransaction();
        try {
            for ($offset = 0; $offset < 4096; $offset += 256) {
                $parameters = [];
                for ($index = $offset; $index < $offset + 256; ++$index) {
                    array_push($parameters, self::id($index), self::id(90), $poison ? '{bad' : json_encode(self::context(), JSON_THROW_ON_ERROR), 1, 1, $poison && $index < 256 ? 105 : 2, 0);
                }

                $connection->executeStatement(
                    'INSERT INTO '.$quotedTable.' (registration_id, owner, context, opened_at, observed_at, expires_at, closed_at) VALUES '.implode(', ', array_fill(0, 256, '(?, ?, ?, ?, ?, ?, ?)')),
                    $parameters
                );
            }

            $connection->commit();
        } catch (Throwable $failure) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $failure;
        }
    }

    private function defaultSnapshot(Connection $connection, DbalSubscriptionTables $tables): array
    {
        $snapshot = [];
        if ($tables->stateTable()->name() !== 'fight_mcp_subscription_state') {
            $snapshot['state'] = $connection->fetchAllAssociative('SELECT * FROM fight_mcp_subscription_state');
        }

        if ($tables->subscriptionTable()->name() !== 'fight_mcp_subscriptions') {
            $snapshot['registrations'] = $connection->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions ORDER BY registration_id');
        }

        return $snapshot;
    }

    private function fails(callable $operation): Throwable
    {
        $failure = null;
        try {
            $operation();
        } catch (Throwable $caught) {
            $failure = $caught;
        }

        self::assertNotNull($failure, 'Selected storage failure must not become fallback, empty success or runtime repair.');

        return $failure;
    }
}
