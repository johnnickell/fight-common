<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Task\Dbal;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskRepository;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTransaction;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DbalTaskTables::class)]
#[CoversClass(DbalTaskSchema::class)]
#[CoversClass(DbalTaskTransaction::class)]
#[CoversClass(DbalTaskRepository::class)]
final class DbalTaskTablesTest extends UnitTestCase
{
    public function test_that_defaults_and_independent_named_overrides_share_the_real_identifier_mechanism(): void
    {
        $defaults = new DbalTaskTables();
        self::assertSame(DbalTaskSchema::TABLES, [$defaults->stateTable()->name(), $defaults->taskTable()->name()]);
        $stateOnly = new DbalTaskTables(stateTable: 'Order');
        self::assertSame('Order', $stateOnly->stateTable()->name());
        self::assertSame('fight_tasks', $stateOnly->taskTable()->name());
        $taskOnly = new DbalTaskTables(taskTable: 'Group');
        self::assertSame('fight_task_state', $taskOnly->stateTable()->name());
        self::assertSame('Group', $taskOnly->taskTable()->name());
        $tables = new DbalTaskTables('Order', '_'.str_repeat('a', 62));
        foreach ([$tables->stateTable(), $tables->taskTable()] as $table) {
            self::assertInstanceOf(DbalTableName::class, $table);
            self::assertSame('`'.$table->name().'`', $table->schemaName());
            self::assertSame('"'.$table->name().'"', $table->quoted(new PostgreSQLPlatform()));
        }
    }

    #[DataProvider('invalidSelections')]
    public function test_that_invalid_or_colliding_selections_reject_before_any_database_effect(array $selection): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DbalTaskTables(...$selection);
    }

    public static function invalidSelections(): iterable
    {
        foreach (['stateTable', 'taskTable'] as $parameter) {
            foreach (['', 'schema.table', 'unsafe; DROP TABLE x', 'two words', '1table', '`quoted`', '"quoted"', 'café', str_repeat('a', 64), "line\n", "nul\0"] as $index => $name) {
                yield $parameter.'-'.$index => [[$parameter => $name]];
            }
        }

        yield 'exact collision' => [['stateTable' => 'same', 'taskTable' => 'same']];
        yield 'case collision' => [['stateTable' => 'Same', 'taskTable' => 'sAME']];
        yield 'default collision' => [['taskTable' => 'FIGHT_TASK_STATE']];
    }

    public function test_that_configured_schema_composition_quotes_assets_without_changing_columns_or_storage_options(): void
    {
        $tables = new DbalTaskTables('Order', 'Group');
        foreach ([new MySQLPlatform(), new PostgreSQLPlatform()] as $platform) {
            $schema = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
            $schema->createTable('unrelated')->addColumn('id', 'integer');
            new DbalTaskSchema($tables)->addTo($schema, $platform);
            self::assertCount(3, $schema->getTables());
            self::assertSame(['scope', 'task_id'], $schema->getTable('Group')->getPrimaryKey()->getColumns());
            self::assertSame(16777215, $schema->getTable('Group')->getColumn('payload')->getLength());
            $sql = implode("\n", $schema->toSql($platform));
            self::assertStringContainsString($tables->stateTable()->quoted($platform), $sql);
            self::assertStringContainsString($tables->taskTable()->quoted($platform), $sql);
            self::assertStringNotContainsString('fight_task', $sql);
            if ($platform instanceof MySQLPlatform) {
                self::assertSame('InnoDB', $schema->getTable('Group')->getOption('engine'));
                self::assertSame('utf8mb4_bin', $schema->getTable('Order')->getOption('collation'));
            }
        }
    }

    public function test_that_overridden_runtime_never_writes_even_the_default_serialization_lock(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        new DbalTaskSchema()->install($connection);
        new TaskFixture($connection)->create(poll: 99);
        $before = $connection->fetchOne('SELECT payload FROM fight_tasks');
        $tables = new DbalTaskTables('Order', 'Group');
        $schema = new DbalTaskSchema($tables);
        $schema->install($connection);
        foreach (['fight_task_state', 'fight_tasks'] as $table) {
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $operation) {
                $connection->executeStatement('CREATE TRIGGER forbid_'.$table.'_'.$operation.' BEFORE '.$operation.' ON '.$table." BEGIN SELECT RAISE(ABORT, 'Default counterpart must not be written'); END");
            }
        }

        $schema->install($connection);
        $fixture = new TaskFixture($connection, $tables);
        self::assertNull($fixture->get());
        $fixture->create(poll: 25);
        $fixture->create(poll: 25);
        $fixture->issue();
        $fixture->cancel();
        $fixture->record(TaskFixture::completed(), 3, 25);
        self::assertSame('completed', $fixture->get()->get('state')->get('status'));
        self::assertSame(25, $fixture->get()->get('poll_interval'));
        $fixture->remove();
        self::assertNull($fixture->get());
        self::assertSame($before, $connection->fetchOne('SELECT payload FROM fight_tasks'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT version FROM fight_task_state'));
        $connection->close();
    }

    #[DataProvider('corruptPayloads')]
    public function test_that_configured_payload_reads_keep_bounds_and_identity_checks_without_default_fallback(string $payload): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        new DbalTaskSchema()->install($connection);
        new TaskFixture($connection)->create();
        $tables = new DbalTaskTables('Order', 'Group');
        new DbalTaskSchema($tables)->install($connection);
        $connection->insert($tables->taskTable()->quoted($connection->getDatabasePlatform()), [
            'scope' => 'scope', 'task_id' => TaskFixture::ID, 'payload' => $payload,
        ]);
        $fixture = new TaskFixture($connection, $tables);
        $this->expectException(TaskStorageFailed::class);
        $fixture->get();
    }

    public static function corruptPayloads(): iterable
    {
        yield 'malformed' => ['{'];
        yield 'oversized' => [str_repeat('x', 40961)];
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 1)->toArray();
        $task['definition']['scope'] = 'other';
        yield 'wrong identity' => [json_encode($task, JSON_THROW_ON_ERROR)];
    }

    public function test_that_repository_and_committed_reader_inherit_the_exact_boundary_selection(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tables = new DbalTaskTables('Order', 'Group');
        $schema = new DbalTaskSchema(tables: $tables);
        $schema->install($connection);
        $schema->install($connection);

        $transactionalUnitOfWork = new DbalTaskTransaction(connection: $connection, tables: $tables);
        self::assertSame($tables, $transactionalUnitOfWork->tables());
        $taskRepository = new DbalTaskRepository(transactionalUnitOfWork: $transactionalUnitOfWork);
        $fixture = new TaskFixture($connection, $tables);
        $fixture->create();
        self::assertSame(1, $taskRepository->read('scope', TaskFixture::id())->version());
        self::assertSame(['Group', 'Order'], $connection->createSchemaManager()->listTableNames());
        $fixture->record(TaskFixture::completed());
        self::assertSame('completed', $taskRepository->read('scope', TaskFixture::id())->state()->status());
        $fixture->remove();
        self::assertNull($taskRepository->read('scope', TaskFixture::id()));
        $connection->close();
    }
}
