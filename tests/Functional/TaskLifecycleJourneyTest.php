<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class TaskLifecycleJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('routedSelection')]
    public function test_that_routed_commands_and_queries_reconstruct_all_five_coherent_states_across_connections(string $backend, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend, $tables); $fixture = new TaskFixture($connection, $tables);
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform());
        $fixture->create(1000, 10); $original = $fixture->get()->toString();
        $params = $connection->getParams(); $connection->close();
        $fresh = new TaskFixture($this->connect($params), $tables);
        self::assertSame($original, $fresh->get()->toString()); $fresh->create(1000, 10);
        $fresh->record(TaskFixture::input());
        $reconnected = new TaskFixture($this->connect($params), $tables);
        self::assertSame('input_required', $reconnected->get()->get('state')->get('status'));
        self::assertSame('form', $reconnected->get()->get('state')->get('input')->get(TaskFixture::KEY_1)->get('type'));
        $reconnected->record(TaskFixture::completed(true), 2);
        self::assertTrue((new TaskFixture($this->connect($params), $tables))->get()->get('state')->get('result')->get('isError'));
        foreach ([TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), TaskState::create('cancelled')] as $index => $terminal) {
            $id = str_repeat((string) ($index + 1), 32);
            $fresh->create(id: $id);
            $fresh->commandBus->execute(new \Fight\Common\Domain\Mcp\Task\Command\RecordTaskState('scope', TaskFixture::id($id), 'producer', 1, $terminal));
            self::assertSame($terminal->data()->toString(), (new TaskFixture($this->connect($params), $tables))->get($id)->get('state')->toString());
        }
        $snapshot = $reconnected->get()->toString(); $reconnected->readAllowed = false; self::assertNull($reconnected->get());
        $reconnected->readAllowed = true; $reconnected->discloseAllowed = false; self::assertNull($reconnected->get());
        $reconnected->discloseAllowed = true; self::assertSame($snapshot, $reconnected->get()->toString());
        self::assertNull($reconnected->get(scope: 'SUPER_ADMIN'));
        $reconnected->now += 1000; self::assertNull($reconnected->get());
        $storage = $this->connect($params);
        self::assertSame(3, (int) $storage->fetchOne('SELECT COUNT(*) FROM '.$taskTable));
        self::assertNotNull($fresh->get());
        $storage->delete($taskTable, ['scope' => 'scope']);
        self::assertNull($fresh->get(), 'A routed read cannot report a previous outcome after storage disappears.');
    }

    #[DataProvider('races')]
    public function test_that_independent_processes_serialize_retries_and_preserve_the_terminal_winner(string $backend, string $mode, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend, $tables); $fixture = new TaskFixture($connection, $tables);
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform());
        $isCreation = in_array($mode, ['duplicate', 'conflict', 'rollback', 'revoked'], true);
        if (!$isCreation) { $fixture->create(); }
        $directory = $this->directory(); $config = $directory.'/config.json';
        file_put_contents($config, json_encode(['params' => $connection->getParams(), 'mode' => $mode, 'stateTable' => $tables->stateTable()->name(), 'taskTable' => $tables->taskTable()->name()], JSON_THROW_ON_ERROR));
        $first = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/state-worker.php', $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/state-worker.php', $config, 'second'], timeout: 25);
        try {
            $first->start(); $this->waitFor($directory.'/first-precommit', $first);
            $second->start(); $this->waitFor($directory.'/second-started', $second); usleep(150000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted', 'Admission must run freshly after obtaining the serialization lock.');
            $before = (new TaskFixture($this->connect($connection->getParams()), $tables))->get();
            if ($isCreation) { self::assertNull($before, 'No uncommitted handle may escape.'); }
            else { self::assertSame('working', $before->get('state')->get('status')); }
            if ($mode === 'revoked') { file_put_contents($directory.'/revoked', 'yes'); }
            file_put_contents($directory.'/release', 'yes');
            self::assertSame(0, $first->wait(), $first->getErrorOutput()); self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $one = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR); $two = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($mode === 'rollback' ? TaskStorageFailed::class : 'committed', $one['status']);
            $expected = match ($mode) { 'conflict', 'late-input', 'late-progress', 'late-cancel' => TaskConflict::class, 'revoked' => TaskRejected::class, default => 'committed' };
            self::assertSame($expected, $two['status']); self::assertSame(1, $two['admissions']);
            self::assertSame($mode === 'rollback' ? 0 : 1, $one['events']);
            self::assertSame($mode === 'rollback' ? 1 : 0, $two['events']);
            $current = (new TaskFixture($this->connect($connection->getParams()), $tables))->get();
            self::assertSame($isCreation ? 1 : 2, $current->get('version'));
            self::assertSame($isCreation ? 'working' : 'completed', $current->get('state')->get('status'));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$taskTable));
        } finally { file_put_contents($directory.'/release', 'yes'); $first->stop(); $second->stop(); }
    }

    #[DataProvider('removalRaces')]
    public function test_that_real_removal_races_hold_current_admission_and_preserve_coherent_reads_through_commit(string $backend, string $mode, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend, $tables); $fixture = new TaskFixture($connection, $tables);
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform());
        if ($mode !== 'create-remove') { $fixture->create(); }
        $directory = $this->directory(); $config = $directory.'/config.json';
        file_put_contents($config, json_encode(['params' => $connection->getParams(), 'mode' => $mode, 'stateTable' => $tables->stateTable()->name(), 'taskTable' => $tables->taskTable()->name()], JSON_THROW_ON_ERROR));
        $first = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/state-worker.php', $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/state-worker.php', $config, 'second'], timeout: 25);
        try {
            $first->start(); $this->waitFor($directory.'/first-precommit', $first);
            $second->start(); $this->waitFor($directory.'/second-started', $second); usleep(150000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted');
            $before = (new TaskFixture($this->connect($connection->getParams()), $tables))->get();
            if ($mode === 'create-remove') { self::assertNull($before); }
            else { self::assertSame('working', $before->get('state')->get('status')); self::assertSame(1, $before->get('version')); }
            if ($mode === 'remove-revoked') { file_put_contents($directory.'/revoked', 'yes'); }
            file_put_contents($directory.'/release', 'yes');
            self::assertSame(0, $first->wait(), $first->getErrorOutput()); self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $one = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR); $two = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($mode === 'remove-rollback-update' ? TaskStorageFailed::class : 'committed', $one['status']);
            self::assertSame(in_array($mode, ['remove-progress', 'remove-outcome', 'remove-revoked'], true) ? TaskRejected::class : 'committed', $two['status']);
            self::assertSame(1, $two['admissions']);
            self::assertSame($mode === 'remove-rollback-update' ? 0 : 1, $one['events']);
            self::assertSame(in_array($mode, ['update-remove', 'create-remove', 'remove-rollback-update', 'remove-create'], true) ? 1 : 0, $two['events']);
            $current = (new TaskFixture($this->connect($connection->getParams()), $tables))->get();
            if (in_array($mode, ['remove-rollback-update', 'remove-create'], true)) {
                self::assertSame($mode === 'remove-create' ? 'working' : 'completed', $current->get('state')->get('status'));
                self::assertSame($mode === 'remove-create' ? 1 : 2, $current->get('version'));
            } else { self::assertNull($current); }
            self::assertSame($current === null ? 0 : 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$taskTable));
        } finally { file_put_contents($directory.'/release', 'yes'); $first->stop(); $second->stop(); }
    }

    public static function removalRaces(): iterable
    {
        // Direct DBAL tests exercise the other write orders and the rollback and revocation variants.
        yield 'sqlite-configured-remove-create' => ['sqlite', 'remove-create', new DbalTaskTables('TaskState', 'TaskRows')];
    }

    public static function routedSelection(): iterable
    {
        yield 'sqlite-configured' => ['sqlite', new DbalTaskTables('TaskState', 'TaskRows')];
    }

    public static function races(): iterable
    {
        yield 'mysql-late-cancel' => ['mysql', 'late-cancel', new DbalTaskTables()];
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }
        foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-task-'); }
        parent::tearDown();
    }

    private function database(string $backend, DbalTaskTables $tables): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/tasks.sqlite']; }
        else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'An actual owned disposable database is required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = $this->connect($params);
        foreach (array_unique([...array_reverse(DbalTaskSchema::TABLES), $tables->taskTable()->name(), $tables->stateTable()->name()]) as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$connection->getDatabasePlatform()->quoteSingleIdentifier($table));
        }
        (new DbalTaskSchema($tables))->install($connection); return $connection;
    }

    private function connect(array $params): Connection
    {
        $connection = DriverManager::getConnection($params);
        if ($params['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
        $this->connections[] = $connection; return $connection;
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir().'/fight-task-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($directory, 0700));
        $this->directories[] = $directory; return $directory;
    }

    private function waitFor(string $path, Process $worker): void
    {
        $deadline = microtime(true) + 15;
        while (!file_exists($path)) {
            if (!$worker->isRunning() || microtime(true) > $deadline) { self::fail('Worker barrier unavailable: '.$worker->getErrorOutput().$worker->getOutput()); }
            usleep(10000);
        }
        self::assertFileExists($path);
    }
}
