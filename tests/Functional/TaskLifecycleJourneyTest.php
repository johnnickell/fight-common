<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Task\Exception\TaskConflict;
use Fight\Common\Application\Task\Exception\TaskRejected;
use Fight\Common\Application\Task\Exception\TaskStorageFailed;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Task\Command\CreateTask;
use Fight\Common\Domain\Task\Event\TaskChanged;
use Fight\Common\Domain\Task\Event\TaskDataRemoved;
use Fight\Common\Domain\Task\TaskState;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class TaskLifecycleJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('databases')]
    public function test_that_routed_commands_and_queries_reconstruct_all_five_coherent_states_across_connections(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection);
        $fixture->create(1000, 10); $original = $fixture->get()->toString();
        $params = $connection->getParams(); $connection->close();
        $fresh = new TaskFixture($this->connect($params));
        self::assertSame($original, $fresh->get()->toString()); $fresh->create(1000, 10);
        $fresh->record(TaskFixture::input());
        $reconnected = new TaskFixture($this->connect($params));
        self::assertSame('input_required', $reconnected->get()->get('state')->get('status'));
        self::assertSame('form', $reconnected->get()->get('state')->get('input')->get('request-1')->get('type'));
        $reconnected->record(TaskFixture::completed(true), 2);
        self::assertTrue((new TaskFixture($this->connect($params)))->get()->get('state')->get('result')->get('isError'));
        foreach ([TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), TaskState::create('cancelled')] as $index => $terminal) {
            $id = str_repeat((string) ($index + 1), 32);
            $fresh->create(id: $id);
            $fresh->commands->execute(new \Fight\Common\Domain\Task\Command\RecordTaskState('scope', $id, 'producer', 1, $terminal));
            self::assertSame($terminal->data()->toString(), (new TaskFixture($this->connect($params)))->get($id)->get('state')->toString());
        }
        $snapshot = $reconnected->get()->toString(); $reconnected->readAllowed = false; self::assertNull($reconnected->get());
        $reconnected->readAllowed = true; $reconnected->discloseAllowed = false; self::assertNull($reconnected->get());
        $reconnected->discloseAllowed = true; self::assertSame($snapshot, $reconnected->get()->toString());
        self::assertNull($reconnected->get(scope: 'SUPER_ADMIN'));
        $reconnected->now += 1000; self::assertNull($reconnected->get());
        self::assertSame(3, (int) $this->connect($params)->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
    }

    #[DataProvider('databases')]
    public function test_that_real_rollback_lost_commit_ack_and_postcommit_failure_preserve_truthful_recovery(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection); $params = $connection->getParams();
        $fixture->create(); $before = $fixture->get()->toString();
        if ($backend === 'sqlite') { $connection->executeStatement("CREATE TRIGGER task_fail BEFORE INSERT ON fight_tasks BEGIN SELECT RAISE(ABORT, 'private'); END"); }
        else { $connection->executeStatement('ALTER TABLE fight_tasks ADD CONSTRAINT task_fail CHECK (payload NOT LIKE \'%completed%\')'); }
        try { $fixture->record(TaskFixture::completed()); self::fail('Constraint failure required.'); } catch (TaskStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); }
        self::assertSame($before, (new TaskFixture($this->connect($params)))->get()->toString());
        if ($backend === 'sqlite') { $connection->executeStatement('DROP TRIGGER task_fail'); }
        elseif ($backend === 'mysql') { $connection->executeStatement('ALTER TABLE fight_tasks DROP CHECK task_fail'); }
        else { $connection->executeStatement('ALTER TABLE fight_tasks DROP CONSTRAINT task_fail'); }
        $proxy = $this->proxy($connection);
        $proxy->shouldReceive('commit')->once()->andReturnUsing(static function () use ($connection): void { $connection->commit(); throw new RuntimeException('Lost actual commit acknowledgment'); });
        $uncertain = new TaskFixture($proxy);
        try { $uncertain->record(TaskFixture::completed()); self::fail('Uncertainty required.'); } catch (TaskStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); }
        self::assertTrue($uncertain->store->isClosed());
        $fresh = new TaskFixture($this->connect($params)); $calls = 0;
        $fresh->events->addHandler(ClassName::underscore(TaskChanged::class), static function (EventMessage $message) use (&$calls, $params): void {
            ++$calls; $other = DriverManager::getConnection($params);
            self::assertSame($message->payload()->toArray()['version'], (new TaskFixture($other))->get($message->payload()->toArray()['id'])->get('version'));
            $other->close(); throw new RuntimeException('Private publication failure');
        });
        $fresh->record(TaskFixture::completed()); self::assertSame(0, $calls, 'Lost acknowledgment retry must not replay publication.');
        try { $fresh->create(id: str_repeat('c', 32)); self::fail('Postcommit failure required.'); } catch (CommittedTaskDispatchFailed $failure) { self::assertSame(1, $failure->event()->toArray()['version']); }
        self::assertNotNull((new TaskFixture($this->connect($params)))->get(str_repeat('c', 32))); self::assertSame(1, $calls);
    }

    #[DataProvider('databases')]
    public function test_that_enclosing_transaction_rejection_never_enlists_or_commits_unrelated_work(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection);
        $connection->executeStatement('CREATE TABLE task_caller_work (id INTEGER)');
        $connection->beginTransaction(); $connection->insert('task_caller_work', ['id' => 7]);
        try { $fixture->create(); self::fail('Outer transaction must reject before admission.'); } catch (LogicException) {
            self::assertSame(0, $fixture->admissions); self::assertTrue($connection->isTransactionActive());
            self::assertSame(7, (int) $connection->fetchOne('SELECT id FROM task_caller_work'));
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
        }
        $connection->commit(); self::assertSame(7, (int) $this->connect($connection->getParams())->fetchOne('SELECT id FROM task_caller_work'));
        $connection->executeStatement('DROP TABLE task_caller_work');
        $fixture->create(); $connection->delete('fight_tasks', ['scope' => 'scope']);
        self::assertNull($fixture->get(), 'Disappeared data cannot manufacture success or cancellation.');
    }

    #[DataProvider('databases')]
    public function test_that_timestamp_ceiling_retries_preserve_retention_and_maximum_polling_advice(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection);
        $fixture->now = \Fight\Common\Domain\Task\Task::MAX_TIME - 100;
        $fixture->create(100, 2147483647);
        $before = $fixture->get()->toString();
        $fixture->now = \Fight\Common\Domain\Task\Task::MAX_TIME - 1;
        $fixture->create(100, 2147483647);
        $fresh = new TaskFixture($this->connect($connection->getParams()));
        $fresh->now = $fixture->now;
        self::assertSame($before, $fresh->get()->toString());
        self::assertSame(2147483647, $fresh->get()->get('poll_interval'));
        $fixture->now = \Fight\Common\Domain\Task\Task::MAX_TIME;
        self::assertNull($fixture->get());
        try { $fixture->create(100, 2147483647); self::fail('Expired retry must not renew retention.'); }
        catch (TaskRejected) { self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks')); }
    }

    #[DataProvider('databases')]
    public function test_that_the_complete_accepted_original_context_depth_survives_reconstruction(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection);
        $nested = 'leaf';
        for ($depth = 0; $depth < 63; ++$depth) { $nested = (object) ['node' => $nested]; }
        $operation = TaskFixture::definition()->operation()->with('retained', $nested, 64);
        $fixture->commands->execute(new CreateTask(TaskFixture::definition(operation: $operation), TaskState::create('working')));
        $fresh = new TaskFixture($this->connect($connection->getParams()));
        self::assertSame('working', $fresh->get()->get('state')->get('status'));
        self::assertSame($operation->toString(), $fresh->store->read('scope', TaskFixture::ID)->definition()->operation()->toString());
        $fresh->commands->execute(new CreateTask(TaskFixture::definition(operation: $operation), TaskState::create('working')));
        self::assertSame(1, $fresh->get()->get('version'));
    }

    #[DataProvider('races')]
    public function test_that_independent_processes_serialize_retries_and_preserve_the_terminal_winner(string $backend, string $mode): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection);
        $isCreation = in_array($mode, ['duplicate', 'conflict', 'rollback', 'revoked'], true);
        if (!$isCreation) { $fixture->create(); }
        $directory = $this->directory(); $config = $directory.'/config.json';
        file_put_contents($config, json_encode(['params' => $connection->getParams(), 'mode' => $mode], JSON_THROW_ON_ERROR));
        $first = new Process([PHP_BINARY, __DIR__.'/../TestCase/Task/state-worker.php', $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../TestCase/Task/state-worker.php', $config, 'second'], timeout: 25);
        try {
            $first->start(); $this->waitFor($directory.'/first-precommit', $first);
            $second->start(); $this->waitFor($directory.'/second-started', $second); usleep(150000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted', 'Admission must run freshly after obtaining the serialization lock.');
            $before = (new TaskFixture($this->connect($connection->getParams())))->get();
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
            $current = (new TaskFixture($this->connect($connection->getParams())))->get();
            self::assertSame($isCreation ? 1 : 2, $current->get('version'));
            self::assertSame($isCreation ? 'working' : 'completed', $current->get('state')->get('status'));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
        } finally { file_put_contents($directory.'/release', 'yes'); $first->stop(); $second->stop(); }
    }

    #[DataProvider('databases')]
    public function test_that_removal_lost_ack_postcommit_failure_and_fresh_retries_never_restore_removed_bodies(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection); $params = $connection->getParams();
        $fixture->create(1000); $fixture->record(TaskFixture::input());
        $before = $fixture->get()->toString();
        $proxy = $this->proxy($connection);
        $proxy->shouldReceive('commit')->once()->andReturnUsing(static function () use ($connection): void { $connection->commit(); throw new RuntimeException('Lost removal acknowledgment'); });
        $uncertain = new TaskFixture($proxy);
        try { $uncertain->remove(); self::fail('Actual committed deletion with lost acknowledgment required.'); }
        catch (TaskStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); }
        self::assertTrue($uncertain->store->isClosed());
        $fresh = new TaskFixture($this->connect($params));
        self::assertNull($fresh->get()); self::assertStringContainsString('input_required', $before);
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
        $fresh->remove(); $fresh->removeAllowed = false;
        try { $fresh->remove(); self::fail('Missing repeat must freshly authorize.'); } catch (TaskRejected) { self::assertCount(2, $fresh->removalChecks); }
        try { $fresh->record(TaskFixture::completed()); self::fail('Late update cannot recreate work.'); } catch (TaskRejected) { self::assertNull($fresh->get()); }
        $fresh->removeAllowed = true; $fresh->now += 500; $fresh->create(2000);
        self::assertSame(1, $fresh->get()->get('version')); self::assertSame($fresh->now, $fresh->get()->get('created_at'));
        $facts = [];
        $fresh->events->addHandler(ClassName::underscore(TaskDataRemoved::class), static function (EventMessage $message) use ($params, &$facts): void {
            $other = DriverManager::getConnection($params);
            try { self::assertNull((new TaskFixture($other))->get()); self::assertSame(0, (int) $other->fetchOne('SELECT COUNT(*) FROM fight_tasks')); }
            finally { $other->close(); }
            $facts[] = $message->payload()->toArray(); throw new RuntimeException('Private publication failure');
        });
        try { $fresh->remove(); self::fail('Dispatch failure required after commit.'); }
        catch (CommittedTaskDispatchFailed $failure) { self::assertSame(['scope' => 'scope', 'ids' => [TaskFixture::ID]], $failure->event()->toArray()); }
        $fresh->remove(); self::assertCount(1, $facts); self::assertNull((new TaskFixture($this->connect($params)))->get());
    }

    #[DataProvider('databases')]
    public function test_that_independent_removal_policy_and_expiry_never_skip_denied_members_or_touch_caller_work(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection); $other = str_repeat('a', 32);
        $fixture->create(10); $fixture->record(TaskFixture::completed()); $fixture->create(id: $other);
        $before = $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id');
        $fixture->deniedRemovalIds = [$other];
        try { $fixture->remove([TaskFixture::ID, $other]); self::fail('One denied member rejects the whole operation.'); }
        catch (TaskRejected) { self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id')); }
        try { $fixture->remove([TaskFixture::ID], 'SUPER_ADMIN'); self::fail('Labels do not authorize cross-scope removal.'); }
        catch (TaskRejected $failure) { self::assertSame('Task operation rejected.', $failure->getMessage()); }
        $fixture->deniedRemovalIds = [];
        $connection->executeStatement('CREATE TABLE task_caller_work (id INTEGER)');
        $connection->beginTransaction(); $connection->insert('task_caller_work', ['id' => 7]); $checks = count($fixture->removalChecks);
        try { $fixture->remove(); self::fail('Removal cannot enlist caller work.'); }
        catch (LogicException) {
            self::assertSame($checks, count($fixture->removalChecks)); self::assertTrue($connection->isTransactionActive());
            self::assertSame(7, (int) $connection->fetchOne('SELECT id FROM task_caller_work'));
            self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id'));
        }
        $connection->commit(); self::assertSame(7, (int) $this->connect($connection->getParams())->fetchOne('SELECT id FROM task_caller_work'));
        $connection->executeStatement('DROP TABLE task_caller_work');
        $fixture->now += 10; self::assertNull($fixture->get()); self::assertNotNull($fixture->get($other));
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id'), 'Reads never clean expired rows or comparison material.');
        $fixture->createAllowed = false; $fixture->updateAllowed = false; $fixture->readAllowed = false;
        $fixture->remove([TaskFixture::ID, $other]); self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
        $fixture->readAllowed = true; self::assertNull($fixture->get()); self::assertNull($fixture->get($other));
    }

    #[DataProvider('removalRaces')]
    public function test_that_real_removal_races_hold_current_admission_and_preserve_coherent_reads_through_commit(string $backend, string $mode): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection);
        if ($mode !== 'create-remove') { $fixture->create(); }
        $directory = $this->directory(); $config = $directory.'/config.json';
        file_put_contents($config, json_encode(['params' => $connection->getParams(), 'mode' => $mode], JSON_THROW_ON_ERROR));
        $first = new Process([PHP_BINARY, __DIR__.'/../TestCase/Task/state-worker.php', $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../TestCase/Task/state-worker.php', $config, 'second'], timeout: 25);
        try {
            $first->start(); $this->waitFor($directory.'/first-precommit', $first);
            $second->start(); $this->waitFor($directory.'/second-started', $second); usleep(150000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted');
            $before = (new TaskFixture($this->connect($connection->getParams())))->get();
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
            $current = (new TaskFixture($this->connect($connection->getParams())))->get();
            if (in_array($mode, ['remove-rollback-update', 'remove-create'], true)) {
                self::assertSame($mode === 'remove-create' ? 'working' : 'completed', $current->get('state')->get('status'));
                self::assertSame($mode === 'remove-create' ? 1 : 2, $current->get('version'));
            } else { self::assertNull($current); }
            self::assertSame($current === null ? 0 : 1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
        } finally { file_put_contents($directory.'/release', 'yes'); $first->stop(); $second->stop(); }
    }

    public static function removalRaces(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            foreach (['remove-progress', 'remove-outcome', 'update-remove', 'create-remove', 'remove-repeat', 'remove-revoked', 'remove-rollback-update', 'remove-create'] as $mode) { yield $backend.'-'.$mode => [$backend, $mode]; }
        }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }

    public static function races(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { foreach (['duplicate', 'conflict', 'rollback', 'revoked', 'late-input', 'late-progress', 'late-cancel', 'outcome-retry'] as $mode) { yield $backend.'-'.$mode => [$backend, $mode]; } }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }
        foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-task-'); }
        parent::tearDown();
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/tasks.sqlite']; }
        else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'An actual owned disposable database is required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = $this->connect($params);
        foreach (array_reverse(DbalTaskSchema::TABLES) as $table) { $connection->executeStatement('DROP TABLE IF EXISTS '.$table); }
        (new DbalTaskSchema())->install($connection); return $connection;
    }

    private function connect(array $params): Connection
    {
        $connection = DriverManager::getConnection($params);
        if ($params['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
        $this->connections[] = $connection; return $connection;
    }

    private function proxy(Connection $connection): Connection
    {
        $proxy = $this->mock(Connection::class);
        foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'delete', 'insert', 'rollBack'] as $method) {
            $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $connection->$method(...$arguments));
        }
        return $proxy;
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
