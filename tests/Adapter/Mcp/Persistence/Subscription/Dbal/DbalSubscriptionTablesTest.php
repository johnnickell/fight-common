<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Subscription\Dbal;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriberReader;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionSchema;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionStore;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionTables;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberAccess;
use Fight\Common\Domain\Mcp\Subscription\Query\ListSubscribers;
use Fight\Common\Domain\Mcp\Subscription\SubscriberSelection;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DbalSubscriberReader::class)]
#[CoversClass(DbalSubscriptionSchema::class)]
#[CoversClass(DbalSubscriptionStore::class)]
#[CoversClass(DbalSubscriptionTables::class)]
final class DbalSubscriptionTablesTest extends UnitTestCase
{
    public function test_that_defaults_and_independent_named_overrides_share_the_identifier_contract(): void
    {
        $defaults = new DbalSubscriptionTables();
        self::assertSame(DbalSubscriptionSchema::TABLES, [
            $defaults->stateTable()->name(), $defaults->subscriptionTable()->name()
        ]);
        $stateOnly = new DbalSubscriptionTables(stateTable: 'Order');
        self::assertSame('Order', $stateOnly->stateTable()->name());
        self::assertSame('fight_mcp_subscriptions', $stateOnly->subscriptionTable()->name());
        $registrationOnly = new DbalSubscriptionTables(subscriptionTable: 'Group');
        self::assertSame('fight_mcp_subscription_state', $registrationOnly->stateTable()->name());
        self::assertSame('Group', $registrationOnly->subscriptionTable()->name());

        $tables = new DbalSubscriptionTables('Order', '_'.str_repeat('a', 62));
        foreach ([$tables->stateTable(), $tables->subscriptionTable()] as $table) {
            self::assertInstanceOf(DbalTableName::class, $table);
            self::assertSame('`'.$table->name().'`', $table->schemaName());
            self::assertSame('`'.$table->name().'`', $table->quoted(new MySQLPlatform()));
            self::assertSame('"'.$table->name().'"', $table->quoted(new PostgreSQLPlatform()));
            self::assertSame('"'.$table->name().'"', $table->quoted(new SQLitePlatform()));
        }
    }

    #[DataProvider('invalidSelections')]
    public function test_that_each_unsafe_or_colliding_selection_rejects_before_database_access(array $selection): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DbalSubscriptionTables(...$selection);
    }

    public static function invalidSelections(): iterable
    {
        foreach (['stateTable', 'subscriptionTable'] as $parameter) {
            foreach ([
                '', 'schema.table', 'unsafe; DROP TABLE x', 'two words', '1table', '`quoted`', '"quoted"',
                'café', str_repeat('a', 64), "line\n", "nul\0", 'table--comment', 'table/*comment*/'
            ] as $index => $name) {
                yield $parameter.'-'.$index => [[$parameter => $name]];
            }
        }

        yield 'exact collision' => [['stateTable' => 'same', 'subscriptionTable' => 'same']];
        yield 'case collision' => [['stateTable' => 'Same', 'subscriptionTable' => 'sAME']];
        yield 'default state collision' => [['subscriptionTable' => 'FIGHT_MCP_SUBSCRIPTION_STATE']];
        yield 'default registration collision' => [['stateTable' => 'FIGHT_MCP_SUBSCRIPTIONS']];
    }

    public function test_that_schema_composition_quotes_names_preserves_shape_and_uses_namespace_specific_indexes(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $tables = new DbalSubscriptionTables('Order', 'Group');
            foreach ([new SQLitePlatform(), new MySQLPlatform(), new PostgreSQLPlatform()] as $platform) {
                $schema = $connection->createSchemaManager()->introspectSchema();
                (new DbalSubscriptionSchema())->addTo($schema, $platform);
                (new DbalSubscriptionSchema(tables: $tables))->addTo($schema, $platform);
                self::assertCount(4, $schema->getTables());
                self::assertSame(['singleton'], $schema->getTable('Order')->getPrimaryKey()->getColumns());
                $registration = $schema->getTable('Group');
                self::assertSame(['registration_id'], $registration->getPrimaryKey()->getColumns());
                self::assertSame(32, $registration->getColumn('owner')->getLength());
                self::assertSame([
                    'registration_id', 'owner', 'context', 'opened_at', 'observed_at', 'expires_at', 'closed_at'
                ], array_values(array_map(static fn ($column) => $column->getName(), $registration->getColumns())));
                $indexName = 'mcp_subscription_expiry_'.substr(hash('sha256', 'Group'), 0, 16);
                self::assertSame(['expires_at'], $registration->getIndex($indexName)->getColumns());
                self::assertTrue($schema->getTable('fight_mcp_subscriptions')->hasIndex('fight_mcp_subscription_expiry'));
                $sql = implode("\n", $schema->toSql($platform));
                self::assertStringContainsString($tables->stateTable()->quoted($platform), $sql);
                self::assertStringContainsString($tables->subscriptionTable()->quoted($platform), $sql);
                if ($platform instanceof MySQLPlatform) {
                    foreach (['Order', 'Group'] as $name) {
                        self::assertSame('InnoDB', $schema->getTable($name)->getOption('engine'));
                        self::assertSame('utf8mb4', $schema->getTable($name)->getOption('charset'));
                        self::assertSame('utf8mb4_bin', $schema->getTable($name)->getOption('collation'));
                    }
                }
            }
        } finally {
            $connection->close();
        }
    }

    public function test_that_configured_lifecycle_and_inspection_never_write_overridden_default_counterparts(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            (new DbalSubscriptionSchema())->install($connection);
            $registrationId = str_repeat('1', 32);
            $ownerId = str_repeat('2', 32);
            $context = [
                'subscriber' => 'agent', 'selection' => ['scopes' => ['scope'], 'filters' => []],
                'requested' => ['toolsListChanged' => true], 'accepted' => ['toolsListChanged' => true]
            ];
            (new DbalSubscriptionStore($connection))->open($registrationId, $ownerId, $context, 100, 105);
            $defaultPayload = $connection->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions');
            $connection->update('fight_mcp_subscription_state', ['version' => 77], ['singleton' => 1]);
            foreach (DbalSubscriptionSchema::TABLES as $table) {
                foreach (['INSERT', 'UPDATE', 'DELETE'] as $operation) {
                    $connection->executeStatement(
                        'CREATE TRIGGER forbid_'.$table.'_'.$operation.' BEFORE '.$operation.' ON '.$table
                        ." BEGIN SELECT RAISE(ABORT, 'Default counterpart must not be written'); END"
                    );
                }
            }

            $tables = new DbalSubscriptionTables('Order', 'Group');
            $schema = new DbalSubscriptionSchema(tables: $tables);
            $schema->install($connection);
            $schema->install($connection);
            $subscriptionStore = new DbalSubscriptionStore(connection: $connection, tables: $tables);
            $reader = new DbalSubscriberReader(connection: $connection, storageScope: 'db', clock: static fn () => 103, tables: $tables);
            $selection = SubscriberSelection::create(['scope']);
            $access = SubscriberAccess::create(['scope']);
            self::assertSame(0, $reader->summarize($selection, $access)->toArray()['subscriptions']);
            $subscriptionStore->open($registrationId, $ownerId, $context, 100, 105);
            $subscriptionStore->heartbeat($registrationId, $ownerId, 101, 106);
            $subscriptionStore->heartbeat($registrationId, $ownerId, 101, 106);
            self::assertSame(1, $reader->summarize($selection, $access)->toArray()['subscriptions']);
            self::assertSame($registrationId, $reader->list(new ListSubscribers($selection), $access)->subscribers()[0]->registrationId());
            $subscriptionStore->close($registrationId, $ownerId, 102);
            self::assertSame(1, $subscriptionStore->cleanup(103, 1));
            self::assertSame(0, $reader->summarize($selection, $access)->toArray()['subscriptions']);
            self::assertSame($defaultPayload, $connection->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions'));
            self::assertSame(77, (int) $connection->fetchOne('SELECT version FROM fight_mcp_subscription_state'));
        } finally {
            $connection->close();
        }
    }

    public function test_that_independent_registration_names_can_install_together_without_index_collision(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            (new DbalSubscriptionSchema())->install($connection);
            $connection->update('fight_mcp_subscription_state', ['version' => 77], ['singleton' => 1]);
            foreach ([new DbalSubscriptionTables('Order', 'Group'), new DbalSubscriptionTables('StateTwo', 'RegistrationsTwo')] as $tables) {
                $schema = new DbalSubscriptionSchema(tables: $tables);
                $schema->install($connection);
                $schema->install($connection);
                $stateName = $tables->stateTable()->quoted($connection->getDatabasePlatform());
                self::assertSame(1, (int) $connection->fetchOne('SELECT version FROM '.$stateName.' WHERE singleton = 1'));
            }

            self::assertSame(77, (int) $connection->fetchOne('SELECT version FROM fight_mcp_subscription_state'));
            self::assertCount(6, $connection->createSchemaManager()->listTableNames());
        } finally {
            $connection->close();
        }
    }
}
