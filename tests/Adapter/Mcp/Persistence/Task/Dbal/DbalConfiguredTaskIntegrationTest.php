<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Domain\Mcp\Task\TaskSelection;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversNothing]
final class DbalConfiguredTaskIntegrationTest extends UnitTestCase
{
    private array $connections = [];
    private array $sqlitePaths = [];

    #[DataProvider('selections')]
    public function test_that_each_selection_owns_lifecycle_and_committed_reads_without_default_fallback(string $backend, array $selection): void
    {
        $connection = $this->database($backend);
        $tables = new DbalTaskTables(...$selection);
        $platform = $connection->getDatabasePlatform();
        $schema = new DbalTaskSchema($tables);
        $schema->install($connection);
        $schema->install($connection);

        $selectedTask = $tables->taskTable()->quoted($platform);
        $selectedState = $tables->stateTable()->quoted($platform);

        // Explicit operator composition adds only the absent default counterparts, never runtime creation.
        $defaults = new DbalTaskTables();
        $manager = $connection->createSchemaManager();
        $definition = $manager->introspectSchema();
        $empty = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
        (new DbalTaskSchema())->addTo($empty, $platform);
        foreach ([$defaults->stateTable(), $defaults->taskTable()] as $table) {
            if (!$definition->hasTable($table->name())) {
                $manager->createTable($empty->getTable($table->schemaName()));
                if ($table->name() === 'fight_task_state') {
                    $connection->insert($table->quoted($platform), ['singleton' => 1, 'version' => 1]);
                }
            }
        }

        $defaultFixture = new TaskFixture($connection);
        if ($tables->taskTable()->name() !== 'fight_tasks') {
            $defaultFixture->create(5000, 99);
        }

        $defaultPayload = $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id');
        if ($tables->stateTable()->name() !== 'fight_task_state') {
            // An incompatible populated default state must be irrelevant to the selected namespace.
            $connection->update('fight_task_state', ['version' => 77], ['singleton' => 1]);
        }

        $schema->install($connection);
        $fixture = new TaskFixture($connection, $tables);
        self::assertNull($fixture->get(), 'A selected empty Task table is not a migration or a default-table read.');
        $fixture->create(1000, 25);
        $original = $fixture->get();
        self::assertSame(25, $original->get('poll_interval'));
        $fixture->create(1000, 25);
        $fresh = new TaskFixture($this->connect($connection->getParams()), $tables);
        self::assertSame($original->toString(), $fresh->get()->toString());
        $fixture->issue(confirmation: true);
        $fixture->supply([TaskFixture::KEY_1 => (object) ['action' => 'accept', 'content' => (object) ['answer' => 'approved']]]);
        self::assertTrue($fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('ready'));
        $fixture->cancel();
        self::assertNotNull($fresh->cancellation()->get('cancellation'));
        $fixture->acquire();
        $fixture->record(TaskFixture::completed(), 5);
        self::assertSame('completed', $fresh->get()->get('state')->get('status'));
        try {
            $fixture->record(TaskState::create('cancelled'), 6);
            self::fail('Terminal state cannot be replaced.');
        } catch (TaskConflict) {
            self::assertSame('completed', $fresh->get()->get('state')->get('status'));
        }

        $before = $connection->fetchOne('SELECT payload FROM '.$selectedTask.' WHERE scope = ?', ['scope']);
        try {
            $fixture->transactionalUnitOfWork->commitTransactional(function () use ($fixture): void {
                $fixture->taskRepository->remove(TaskSelection::fromIds('scope', [TaskFixture::id()]));
                throw new RuntimeException('Confirmed rollback');
            });
            self::fail('Expected rollback.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Confirmed rollback', $runtimeException->getMessage());
        }

        self::assertSame($before, $connection->fetchOne('SELECT payload FROM '.$selectedTask.' WHERE scope = ?', ['scope']));
        $fresh->now += 1000;
        self::assertNull($fresh->get());
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$selectedTask));
        try {
            $fresh->create(1000, 25);
            self::fail('Expiry must not renew retention.');
        } catch (TaskRejected) {
            self::assertNull($fresh->get());
        }

        $fresh->remove();
        $fresh->remove();
        self::assertNull($fixture->get());
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$selectedTask));
        self::assertSame(1, (int) $connection->fetchOne('SELECT version FROM '.$selectedState.' WHERE singleton = 1'));
        if ($tables->taskTable()->name() !== 'fight_tasks') {
            self::assertSame($defaultPayload, $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id'));
        }

        if ($tables->stateTable()->name() !== 'fight_task_state') {
            self::assertSame(77, (int) $connection->fetchOne('SELECT version FROM fight_task_state WHERE singleton = 1'));
        }
    }

    #[DataProvider('databases')]
    public function test_that_changed_missing_partial_and_incompatible_selections_never_install_or_copy_at_runtime(string $backend): void
    {
        $connection = $this->database($backend);
        (new DbalTaskSchema())->install($connection);
        $defaultFixture = new TaskFixture($connection);
        $defaultFixture->create();

        $before = $connection->fetchAllAssociative('SELECT * FROM fight_tasks');
        $tables = new DbalTaskTables('Order', 'Group');
        $selected = new TaskFixture($connection, $tables);
        foreach ([fn () => $selected->get(), fn () => $selected->create()] as $operation) {
            try { $operation(); self::fail('Missing selected storage must fail explicitly.'); }
            catch (TaskStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); }
        }

        self::assertFalse($connection->createSchemaManager()->tablesExist(['Group']));
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
        $schema = new DbalTaskSchema($tables);
        $schema->install($connection);
        self::assertNull($selected->get());
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
        $stateTable = $tables->stateTable()->quoted($connection->getDatabasePlatform());
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform());
        $connection->update($stateTable, ['version' => 2], ['singleton' => 1]);
        try { $schema->install($connection); self::fail('Selected incompatible version must reject.'); }
        catch (RuntimeException $runtimeException) { self::assertSame('Unsupported Task schema.', $runtimeException->getMessage()); }

        try { $selected->create(); self::fail('Runtime must check selected version.'); }
        catch (RuntimeException $runtimeException) { self::assertSame('Unsupported Task schema.', $runtimeException->getMessage()); }

        $connection->update($stateTable, ['version' => 1], ['singleton' => 1]);
        $connection->executeStatement('ALTER TABLE '.$taskTable.' DROP COLUMN payload');
        try { $schema->install($connection); self::fail('Selected incompatible payload schema must reject.'); }
        catch (RuntimeException $runtimeException) { self::assertSame('Unsupported Task schema.', $runtimeException->getMessage()); }

        $connection->executeStatement('DROP TABLE '.$taskTable);
        try { $schema->install($connection); self::fail('Partial selected installation must reject.'); }
        catch (RuntimeException $runtimeException) { self::assertSame('Incomplete Task schema.', $runtimeException->getMessage()); }

        self::assertFalse($connection->createSchemaManager()->tablesExist(['Group']));
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
    }

    public static function selections(): iterable
    {
        foreach (self::databases() as $backend => [$name]) {
            foreach (['defaults' => [], 'state-only' => ['stateTable' => 'Order'], 'task-only' => ['taskTable' => 'Group'], 'both' => ['stateTable' => 'Order', 'taskTable' => 'Group']] as $mode => $selection) {
                yield $backend.'-'.$mode => [$name, $selection];
            }
        }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }

        foreach ($this->sqlitePaths as $path) { unlink($path); }

        parent::tearDown();
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') {
            $path = tempnam(sys_get_temp_dir(), 'task-tables-');
            $this->sqlitePaths[] = $path;
            $params = ['driver' => 'pdo_sqlite', 'path' => $path];
        } else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual disposable server database required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }

        $connection = $this->connect($params);
        $platform = $connection->getDatabasePlatform();
        foreach (['Group', 'Order', ...array_reverse(DbalTaskSchema::TABLES)] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$platform->quoteSingleIdentifier($table));
        }

        return $connection;
    }

    private function connect(array $params): Connection
    {
        $connection = DriverManager::getConnection($params);
        $this->connections[] = $connection;
        return $connection;
    }
}
