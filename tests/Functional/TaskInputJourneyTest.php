<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Mcp\Task\TaskCheckConnection;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class TaskInputJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('databases')]
    public function test_that_real_issuance_reconstruction_partial_admission_and_protected_single_use_continuation_survive_reconnect(string $backend, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend, $tables); $params = $connection->getParams(); $fixture = new TaskFixture($connection, $tables);
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform());
        $fixture->create(); $fixture->issue(confirmation: true, count: 2);
        $fresh = new TaskFixture($this->connect($params), $tables);
        self::assertSame([TaskFixture::KEY_1, TaskFixture::KEY_2], array_keys($fresh->get()->get('state')->get('input')->properties()));
        $fresh->supply([TaskFixture::KEY_1 => self::accept('yes'), 'unknown' => ['untrusted' => false]]);
        $fresh = new TaskFixture($this->connect($params), $tables);
        self::assertSame([TaskFixture::KEY_2], array_keys($fresh->get()->get('state')->get('input')->properties()));
        try { $fresh->acquire(); self::fail('Partial approval cannot continue.'); } catch (TaskConflict) { self::assertFalse($fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed')); }
        try { $fresh->supply([TaskFixture::KEY_1 => false, TaskFixture::KEY_2 => ['action' => 'accept', 'content' => (object) ['answer' => false]]]); self::fail('Invalid active answer rejects.'); } catch (TaskRejected) { self::assertCount(1, $fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('answers')->properties()); }
        $fresh->supply([TaskFixture::KEY_1 => false, TaskFixture::KEY_2 => self::accept('yes')]);
        $fresh = new TaskFixture($this->connect($params), $tables);
        $fresh->continueAllowed = false;
        try { $fresh->acquire(); self::fail('Business permission must be current.'); } catch (TaskRejected) { self::assertTrue($fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('ready')); }
        $fresh->continueAllowed = true; $fresh->acquire();
        // Consumer failure/disconnect occurs after durable acquisition; no external effect is claimed.
        $fresh = new TaskFixture($this->connect($params), $tables); $group = $fresh->observe()->get('groups')->get(TaskFixture::KEY_1);
        self::assertTrue($group->get('consumed')); self::assertFalse($group->get('ready'));
        self::assertSame('form-0', $group->get('names')->get(TaskFixture::KEY_1));
        self::assertSame('yes', $group->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer'));
        try { $fresh->acquire(); self::fail('Consumption cannot be restored after consumer failure.'); } catch (TaskConflict) { self::assertTrue($fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed')); }
        $fresh->observeAllowed = false; self::assertNull($fresh->observe());
        $fresh->submitAllowed = false;
        try { $fresh->supply([]); self::fail('Even retries reauthorize.'); } catch (TaskRejected) { self::assertSame(5, $fresh->get()->get('version')); }
        $fresh->observeAllowed = true; self::assertNull($fresh->observe(scope: 'other'));
        self::assertSame([], $fresh->observe('other')->get('groups')->properties());
        $other = str_repeat('a', 32); $fresh->create(id: $other); $fresh->issue(confirmation: true, id: $other);
        $otherKey = array_key_first($fresh->get($other)->get('state')->get('input')->properties());
        self::assertNotSame(TaskFixture::KEY_1, $otherKey, 'Request keys bind the Task and scope, not just a round counter.');
        $fresh->submitAllowed = true;
        $fresh->supply([TaskFixture::KEY_1 => self::accept('transplanted')], id: $other);
        self::assertSame([], $fresh->observe(id: $other)->get('groups')->get($otherKey)->get('answers')->properties());
        $fresh->supply([$otherKey => self::accept('current')], id: $other);
        self::assertSame('current', $fresh->observe(id: $other)->get('groups')->get($otherKey)->get('answers')->get($otherKey)->get('content')->get('answer'));
        $fresh->remove([TaskFixture::ID, $other]); self::assertNull((new TaskFixture($this->connect($params), $tables))->observe());
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$taskTable));
    }

    #[DataProvider('statusMessages')]
    public function test_that_reconstructed_query_preserves_producer_message_while_input_remains_without_mutating_storage(string $backend, ?string $message, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend, $tables);
        $fixture = new TaskFixture($connection, $tables);
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform());
        $fixture->create();
        $issuedAt = $fixture->now;
        $fixture->issue(expiresAt: $issuedAt + 100);
        $fixture->issue(expectedVersion: 2, expiresAt: $issuedAt + 200);
        $requests = $fixture->get()->get('state')->get('input');
        $fixture->commandBus->execute(new RecordTaskState(
            'scope', TaskFixture::id(), 'producer', 3, TaskState::create('input_required', $message, $requests)
        ));
        $before = $connection->fetchOne('SELECT payload FROM '.$taskTable);
        $stored = $fixture->committedTaskReader->read('scope', TaskFixture::id());
        self::assertSame($message, $stored->state()->data()->get('message'));

        foreach ([0, 99, 100, 199, 200] as $elapsed) {
            $fresh = new TaskFixture($this->connect($connection->getParams()), $tables);
            $fresh->now = $issuedAt + $elapsed;
            $view = $fresh->get();
            $state = $view->get('state');
            $expectedKeys = [TaskFixture::KEY_1, TaskFixture::KEY_2];
            if ($elapsed >= 100) {
                $expectedKeys = [TaskFixture::KEY_2];
            }
            if ($elapsed >= 200) {
                $expectedKeys = [];
            }
            self::assertSame($elapsed < 200 ? 'input_required' : 'working', $state->get('status'));
            self::assertSame($elapsed < 200 ? $message : null, $state->get('message'));
            $input = $state->get('input');
            self::assertSame($expectedKeys, $input === null ? [] : array_keys($input->properties()));
            foreach ($expectedKeys as $key) {
                self::assertSame($requests->get($key)->toString(), $state->get('input')->get($key)->toString());
            }
            self::assertSame(4, $view->get('version'));
            self::assertSame($issuedAt, $view->get('updated_at'));
            self::assertSame($before, $connection->fetchOne('SELECT payload FROM '.$taskTable));
        }
    }

    #[DataProvider('databases')]
    public function test_that_real_rollback_uncertain_commit_and_postcommit_failure_preserve_truthful_input_and_caller_work(string $backend, DbalTaskTables $tables): void
    {
        $taskConnection = $this->database($backend, $tables); $params = $taskConnection->getParams(); $fixture = new TaskFixture($taskConnection, $tables); $fixture->create();
        $taskTable = $tables->taskTable()->quoted($taskConnection->getDatabasePlatform());
        foreach (['issue', 'supply', 'acquire'] as $stage) {
            $connection = $this->checkConnection($params); $candidate = new TaskFixture($connection, $tables);
            $operation = match ($stage) { 'issue' => fn () => $candidate->issue(), 'supply' => fn () => $candidate->supply([TaskFixture::KEY_1 => self::accept('valid')]), default => fn () => $candidate->acquire() };
            $before = $taskConnection->fetchOne('SELECT payload FROM '.$taskTable);
            $connection->beforeCommit = static fn () => throw new RuntimeException('Injected before actual commit');
            try { $operation(); self::fail('Precommit rollback required.'); } catch (TaskStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); }
            self::assertSame($before, $this->connect($params)->fetchOne('SELECT payload FROM '.$taskTable));
            $connection = $this->checkConnection($params); $candidate = new TaskFixture($connection, $tables);
            $connection->afterCommit = static fn () => throw new RuntimeException('Lost actual commit acknowledgment');
            try {
                match ($stage) { 'issue' => $candidate->issue(), 'supply' => $candidate->supply([TaskFixture::KEY_1 => self::accept('valid')]), default => $candidate->acquire() };
                self::fail('Lost commit acknowledgment required.');
            } catch (TaskStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); self::assertTrue($candidate->transactionalUnitOfWork->isClosed()); }
            $fresh = new TaskFixture($this->connect($params), $tables); $group = $fresh->observe()->get('groups')->get(TaskFixture::KEY_1);
            self::assertNotNull($group);
            if ($stage === 'supply') { self::assertSame('valid', $group->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer')); }
            if ($stage === 'acquire') { self::assertTrue($group->get('consumed')); }
        }
        $fresh = new TaskFixture($this->connect($params), $tables); $calls = 0;
        $fresh->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$calls): void { ++$calls; throw new RuntimeException('Private postcommit delivery'); });
        $fresh->supply([TaskFixture::KEY_1 => false]); self::assertSame(0, $calls);
        try { $fresh->issue(expectedVersion: 4); self::fail('Durable issue must precede failed fact delivery.'); } catch (CommittedTaskDispatchFailed) { self::assertSame([TaskFixture::KEY_2], array_keys((new TaskFixture($this->connect($params), $tables))->get()->get('state')->get('input')->properties())); }
        $taskConnection->executeStatement('CREATE TABLE input_caller_work (id INTEGER)'); $taskConnection->beginTransaction(); $taskConnection->insert('input_caller_work', ['id' => 7]);
        foreach ([fn () => $fixture->supply([]), fn () => $fixture->issue(expectedVersion: 5), fn () => $fixture->acquire()] as $operation) {
            try { $operation(); self::fail('Caller transaction is unsupported.'); } catch (LogicException) { self::assertTrue($taskConnection->isTransactionActive()); self::assertSame(7, (int) $taskConnection->fetchOne('SELECT id FROM input_caller_work')); }
        }
        $taskConnection->commit(); self::assertSame(7, (int) $this->connect($params)->fetchOne('SELECT id FROM input_caller_work'));
        $taskConnection->executeStatement('DROP TABLE input_caller_work');
    }

    #[DataProvider('databases')]
    public function test_that_real_constraint_rollback_never_partially_consumes_a_valid_batch(string $backend, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend, $tables); $fixture = new TaskFixture($connection, $tables); $fixture->create(); $fixture->issue(count: 2);
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform()); $before = $connection->fetchOne('SELECT payload FROM '.$taskTable);
        if ($backend === 'sqlite') { $connection->executeStatement("CREATE TRIGGER fail_input BEFORE INSERT ON ".$taskTable." BEGIN SELECT RAISE(ABORT, 'private'); END"); }
        else { $connection->executeStatement("ALTER TABLE ".$taskTable." ADD CONSTRAINT fail_input CHECK (payload NOT LIKE '%accepted-body%')"); }
        try { $fixture->supply([TaskFixture::KEY_1 => self::accept('accepted-body'), TaskFixture::KEY_2 => self::accept('accepted-body')]); self::fail('Constraint failure required.'); }
        catch (TaskStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); }
        self::assertSame($before, $this->connect($connection->getParams())->fetchOne('SELECT payload FROM '.$taskTable));
        if ($backend === 'sqlite') { $connection->executeStatement('DROP TRIGGER fail_input'); }
        elseif ($backend === 'mysql') { $connection->executeStatement('ALTER TABLE '.$taskTable.' DROP CHECK fail_input'); }
        else { $connection->executeStatement('ALTER TABLE '.$taskTable.' DROP CONSTRAINT fail_input'); }
        $fixture->supply([TaskFixture::KEY_1 => self::accept('accepted-body'), TaskFixture::KEY_2 => self::accept('accepted-body')]); self::assertTrue($fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('ready'));
    }

    #[DataProvider('databases')]
    public function test_that_reconstructed_supersession_expiry_and_seeded_lifetime_exhaustion_preserve_finite_identity(string $backend, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend, $tables); $fixture = new TaskFixture($connection, $tables); $fixture->create(); $fixture->issue(confirmation: true, count: 2);
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform());
        $fixture->supply([TaskFixture::KEY_1 => self::accept('partial')]);
        $fixture->commandBus->execute(new \Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput('scope', TaskFixture::id(), 'producer', 3, TaskFixture::KEY_1));
        $fixture = new TaskFixture($this->connect($connection->getParams()), $tables); $fixture->issue(expectedVersion: 4);
        self::assertSame([TaskFixture::KEY_3], array_keys($fixture->get()->get('state')->get('input')->properties()));
        $before = $connection->fetchOne('SELECT payload FROM '.$taskTable);
        $fixture->supply([TaskFixture::KEY_1 => false, TaskFixture::KEY_2 => false]);
        self::assertSame($before, $connection->fetchOne('SELECT payload FROM '.$taskTable));
        try { $fixture->supply([TaskFixture::KEY_3 => self::accept(str_repeat('é', 17000))]); self::fail('All encoded bytes are bounded.'); }
        catch (\Fight\Common\Domain\Exception\DomainException) { self::assertSame($before, $connection->fetchOne('SELECT payload FROM '.$taskTable)); }
        $fixture->now += 1000; $fixture->supply([TaskFixture::KEY_3 => self::accept('expired')]);
        self::assertTrue($fixture->observe()->get('groups')->get(TaskFixture::KEY_3)->get('expired'));
        self::assertSame([], $fixture->observe()->get('groups')->get(TaskFixture::KEY_3)->get('answers')->properties());
        $fixture->commandBus->execute(new \Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput('scope', TaskFixture::id(), 'producer', 5, TaskFixture::KEY_3));
        // Seed the documented near-exhaustion value; direct units additionally execute all 1,024 rounds.
        $data = $fixture->committedTaskReader->read('scope', TaskFixture::id())->toArray(); $data['inputs'] = $data['inputs']->with('issued', 1023);
        $fixture->transactionalUnitOfWork->commitTransactional(fn () => $fixture->taskRepository->save(\Fight\Common\Domain\Mcp\Task\Task::fromArray($data)));
        $fixture->issue(expectedVersion: 6); $last = TaskFixture::KEY_PREFIX.'1024';
        self::assertSame([$last], array_keys($fixture->get()->get('state')->get('input')->properties()));
        $fixture->commandBus->execute(new \Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput('scope', TaskFixture::id(), 'producer', 7, $last));
        $fresh = new TaskFixture($this->connect($connection->getParams()), $tables); $before = $connection->fetchOne('SELECT payload FROM '.$taskTable);
        try { $fresh->issue(expectedVersion: 8); self::fail('Lifetime exhaustion cannot wrap after payload retirement.'); }
        catch (TaskConflict) { self::assertSame($before, $connection->fetchOne('SELECT payload FROM '.$taskTable)); }
        self::assertSame([], $fresh->observe()->get('groups')->properties());
    }

    #[DataProvider('races')]
    public function test_that_independent_processes_admit_once_and_preserve_confirmation_and_terminal_winners(string $backend, string $mode, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend, $tables); $fixture = new TaskFixture($connection, $tables); $fixture->create();
        $fixture->issue(confirmation: in_array($mode, ['refusal', 'partial-refusal', 'acquire'], true), count: $mode === 'partial-refusal' ? 2 : 1);
        if ($mode === 'acquire') { $fixture->supply([TaskFixture::KEY_1 => self::accept('first-answer')]); }
        $directory = $this->directory(); $config = $directory.'/config.json';
        file_put_contents($config, json_encode(['params' => $connection->getParams(), 'mode' => $mode, 'stateTable' => $tables->stateTable()->name(), 'taskTable' => $tables->taskTable()->name()], JSON_THROW_ON_ERROR));
        $first = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/input-worker.php', $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/input-worker.php', $config, 'second'], timeout: 25);
        try {
            $first->start(); $this->waitFor($directory.'/first-precommit', $first);
            $second->start(); $this->waitFor($directory.'/second-started', $second); usleep(120000);
            self::assertTrue($second->isRunning()); self::assertFileDoesNotExist($directory.'/second-admitted');
            if ($mode === 'revoked') { file_put_contents($directory.'/revoked', 'yes'); }
            file_put_contents($directory.'/release', 'yes');
            self::assertSame(0, $first->wait(), $first->getErrorOutput()); self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $one = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR); $two = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('committed', $one['status']); self::assertSame(1, $one['events']);
            self::assertSame(match ($mode) { 'input-first', 'acquire' => TaskConflict::class, 'revoked' => TaskRejected::class, default => 'committed' }, $two['status']);
            self::assertSame($mode === 'partial-refusal' ? 1 : 0, $two['events']);
            $fresh = new TaskFixture($this->connect($connection->getParams()), $tables); $group = $fresh->observe()->get('groups')->get(TaskFixture::KEY_1);
            $expectedStatus = match ($mode) { 'completed-first' => 'completed', 'failed-first' => 'failed', 'cancelled-first' => 'cancelled', default => 'working' };
            self::assertSame($expectedStatus, $fresh->get()->get('state')->get('status'));
            if (in_array($mode, ['completed-first', 'failed-first', 'cancelled-first'], true)) { self::assertSame([], $group->get('answers')->properties()); }
            elseif ($mode === 'refusal') { self::assertTrue($group->get('refused')); self::assertSame('decline', $group->get('answers')->get(TaskFixture::KEY_1)->get('action')); }
            else { self::assertSame('first-answer', $group->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer')); }
            if ($mode === 'partial-refusal') { self::assertTrue($group->get('refused')); }
            if ($mode === 'acquire') { self::assertTrue($group->get('consumed')); }
        } finally { file_put_contents($directory.'/release', 'yes'); $first->stop(); $second->stop(); }
    }

    public static function statusMessages(): iterable
    {
        foreach (self::databases() as $database => [$backend, $tables]) {
            foreach (['nonempty' => 'Waiting for both authorized answers', 'empty' => '', 'null' => null] as $name => $message) {
                yield $database.'-'.$name => [$backend, $message, $tables];
            }
        }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend => [$backend, new DbalTaskTables()];
            yield $backend.'-configured' => [$backend, new DbalTaskTables('TaskState', 'TaskRows')];
        }
    }
    public static function races(): iterable { foreach (self::databases() as $name => [$backend, $tables]) { foreach (['duplicate', 'conflict', 'completed-first', 'failed-first', 'cancelled-first', 'input-first', 'refusal', 'partial-refusal', 'acquire', 'revoked'] as $mode) { yield $name.'-'.$mode => [$backend, $mode, $tables]; } } }
    protected function tearDown(): void { foreach ($this->connections as $connection) { $connection->close(); } foreach ($this->directories as $temporaryDirectory) { $this->removeTemporaryDirectory($temporaryDirectory, 'fight-task-input-'); } parent::tearDown(); }
    private static function accept(string $answer): array { return ['action' => 'accept', 'content' => (object) ['answer' => $answer]]; }
    private function database(string $backend, DbalTaskTables $tables): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/input.sqlite']; }
        else { $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Actual disposable server required.'); $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn); }
        $connection = $this->connect($params);
        foreach (array_unique([...array_reverse(DbalTaskSchema::TABLES), $tables->taskTable()->name(), $tables->stateTable()->name()]) as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$connection->getDatabasePlatform()->quoteSingleIdentifier($table));
        }
        (new DbalTaskSchema($tables))->install($connection); return $connection;
    }
    private function connect(array $params): Connection { $connection = DriverManager::getConnection($params); if ($params['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); } $this->connections[] = $connection; return $connection; }
    private function checkConnection(array $params): TaskCheckConnection { $connection = new TaskCheckConnection($params, DriverManager::getConnection($params)->getDriver()); $this->connections[] = $connection; return $connection; }
    private function directory(): string { $temporaryDirectory = sys_get_temp_dir().'/fight-task-input-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($temporaryDirectory, 0700)); $this->directories[] = $temporaryDirectory; return $temporaryDirectory; }
    private function waitFor(string $path, Process $worker): void { $deadline = microtime(true) + 15; while (!file_exists($path)) { if (!$worker->isRunning() || microtime(true) > $deadline) { self::fail('Barrier unavailable: '.$worker->getErrorOutput().$worker->getOutput()); } usleep(10000); } self::assertFileExists($path); }
}
