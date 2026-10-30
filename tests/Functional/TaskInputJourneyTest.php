<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskSchema;
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
    public function test_that_real_issuance_reconstruction_partial_admission_and_protected_single_use_continuation_survive_reconnect(string $backend): void
    {
        $c = $this->database($backend); $params = $c->getParams(); $f = new TaskFixture($c);
        $f->create(); $f->issue(confirmation: true, count: 2);
        $fresh = new TaskFixture($this->connect($params));
        self::assertSame([TaskFixture::KEY_1, TaskFixture::KEY_2], array_keys($fresh->get()->get('state')->get('input')->properties()));
        $fresh->supply([TaskFixture::KEY_1 => self::accept('yes'), 'unknown' => ['untrusted' => false]]);
        $fresh = new TaskFixture($this->connect($params));
        self::assertSame([TaskFixture::KEY_2], array_keys($fresh->get()->get('state')->get('input')->properties()));
        try { $fresh->acquire(); self::fail('Partial approval cannot continue.'); } catch (TaskConflict) { self::assertFalse($fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed')); }
        try { $fresh->supply([TaskFixture::KEY_1 => false, TaskFixture::KEY_2 => ['action' => 'accept', 'content' => (object) ['answer' => false]]]); self::fail('Invalid active answer rejects.'); } catch (TaskRejected) { self::assertCount(1, $fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('answers')->properties()); }
        $fresh->supply([TaskFixture::KEY_1 => false, TaskFixture::KEY_2 => self::accept('yes')]);
        $fresh = new TaskFixture($this->connect($params));
        $fresh->continueAllowed = false;
        try { $fresh->acquire(); self::fail('Business permission must be current.'); } catch (TaskRejected) { self::assertTrue($fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('ready')); }
        $fresh->continueAllowed = true; $fresh->acquire();
        // Consumer failure/disconnect occurs after durable acquisition; no external effect is claimed.
        $fresh = new TaskFixture($this->connect($params)); $group = $fresh->observe()->get('groups')->get(TaskFixture::KEY_1);
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
        $fresh->remove([TaskFixture::ID, $other]); self::assertNull((new TaskFixture($this->connect($params)))->observe());
        self::assertSame(0, (int) $c->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
    }

    #[DataProvider('statusMessages')]
    public function test_that_reconstructed_query_preserves_producer_message_while_input_remains_without_mutating_storage(string $backend, ?string $message): void
    {
        $connection = $this->database($backend);
        $fixture = new TaskFixture($connection);
        $fixture->create();
        $issuedAt = $fixture->now;
        $fixture->issue(expiresAt: $issuedAt + 100);
        $fixture->issue(expectedVersion: 2, expiresAt: $issuedAt + 200);
        $requests = $fixture->get()->get('state')->get('input');
        $fixture->commands->execute(new RecordTaskState(
            'scope', TaskFixture::ID, 'producer', 3, TaskState::create('input_required', $message, $requests)
        ));
        $before = $connection->fetchOne('SELECT payload FROM fight_tasks');
        $stored = $fixture->store->read('scope', TaskFixture::ID);
        self::assertSame($message, $stored->state()->data()->get('message'));

        foreach ([0, 99, 100, 199, 200] as $elapsed) {
            $fresh = new TaskFixture($this->connect($connection->getParams()));
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
            self::assertSame($before, $connection->fetchOne('SELECT payload FROM fight_tasks'));
        }
    }

    #[DataProvider('databases')]
    public function test_that_real_rollback_uncertain_commit_and_postcommit_failure_preserve_truthful_input_and_caller_work(string $backend): void
    {
        $c = $this->database($backend); $params = $c->getParams(); $f = new TaskFixture($c); $f->create();
        foreach (['issue', 'supply', 'acquire'] as $stage) {
            $connection = $this->checkConnection($params); $candidate = new TaskFixture($connection);
            $operation = match ($stage) { 'issue' => fn () => $candidate->issue(), 'supply' => fn () => $candidate->supply([TaskFixture::KEY_1 => self::accept('valid')]), default => fn () => $candidate->acquire() };
            $before = $c->fetchOne('SELECT payload FROM fight_tasks');
            $connection->beforeCommit = static fn () => throw new RuntimeException('Injected before actual commit');
            try { $operation(); self::fail('Precommit rollback required.'); } catch (TaskStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); }
            self::assertSame($before, $this->connect($params)->fetchOne('SELECT payload FROM fight_tasks'));
            $connection = $this->checkConnection($params); $candidate = new TaskFixture($connection);
            $connection->afterCommit = static fn () => throw new RuntimeException('Lost actual commit acknowledgment');
            try {
                match ($stage) { 'issue' => $candidate->issue(), 'supply' => $candidate->supply([TaskFixture::KEY_1 => self::accept('valid')]), default => $candidate->acquire() };
                self::fail('Lost commit acknowledgment required.');
            } catch (TaskStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); self::assertTrue($candidate->store->isClosed()); }
            $fresh = new TaskFixture($this->connect($params)); $group = $fresh->observe()->get('groups')->get(TaskFixture::KEY_1);
            self::assertNotNull($group);
            if ($stage === 'supply') { self::assertSame('valid', $group->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer')); }
            if ($stage === 'acquire') { self::assertTrue($group->get('consumed')); }
        }
        $fresh = new TaskFixture($this->connect($params)); $calls = 0;
        $fresh->events->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$calls): void { ++$calls; throw new RuntimeException('Private postcommit delivery'); });
        $fresh->supply([TaskFixture::KEY_1 => false]); self::assertSame(0, $calls);
        try { $fresh->issue(expectedVersion: 4); self::fail('Durable issue must precede failed fact delivery.'); } catch (CommittedTaskDispatchFailed) { self::assertSame([TaskFixture::KEY_2], array_keys((new TaskFixture($this->connect($params)))->get()->get('state')->get('input')->properties())); }
        $c->executeStatement('CREATE TABLE input_caller_work (id INTEGER)'); $c->beginTransaction(); $c->insert('input_caller_work', ['id' => 7]);
        foreach ([fn () => $f->supply([]), fn () => $f->issue(expectedVersion: 5), fn () => $f->acquire()] as $operation) {
            try { $operation(); self::fail('Caller transaction is unsupported.'); } catch (LogicException) { self::assertTrue($c->isTransactionActive()); self::assertSame(7, (int) $c->fetchOne('SELECT id FROM input_caller_work')); }
        }
        $c->commit(); self::assertSame(7, (int) $this->connect($params)->fetchOne('SELECT id FROM input_caller_work'));
        $c->executeStatement('DROP TABLE input_caller_work');
    }

    #[DataProvider('databases')]
    public function test_that_real_constraint_rollback_never_partially_consumes_a_valid_batch(string $backend): void
    {
        $c = $this->database($backend); $f = new TaskFixture($c); $f->create(); $f->issue(count: 2); $before = $c->fetchOne('SELECT payload FROM fight_tasks');
        if ($backend === 'sqlite') { $c->executeStatement("CREATE TRIGGER fail_input BEFORE INSERT ON fight_tasks BEGIN SELECT RAISE(ABORT, 'private'); END"); }
        else { $c->executeStatement("ALTER TABLE fight_tasks ADD CONSTRAINT fail_input CHECK (payload NOT LIKE '%accepted-body%')"); }
        try { $f->supply([TaskFixture::KEY_1 => self::accept('accepted-body'), TaskFixture::KEY_2 => self::accept('accepted-body')]); self::fail('Constraint failure required.'); }
        catch (TaskStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); }
        self::assertSame($before, $this->connect($c->getParams())->fetchOne('SELECT payload FROM fight_tasks'));
        if ($backend === 'sqlite') { $c->executeStatement('DROP TRIGGER fail_input'); }
        elseif ($backend === 'mysql') { $c->executeStatement('ALTER TABLE fight_tasks DROP CHECK fail_input'); }
        else { $c->executeStatement('ALTER TABLE fight_tasks DROP CONSTRAINT fail_input'); }
        $f->supply([TaskFixture::KEY_1 => self::accept('accepted-body'), TaskFixture::KEY_2 => self::accept('accepted-body')]); self::assertTrue($f->observe()->get('groups')->get(TaskFixture::KEY_1)->get('ready'));
    }

    #[DataProvider('databases')]
    public function test_that_reconstructed_supersession_expiry_and_seeded_lifetime_exhaustion_preserve_finite_identity(string $backend): void
    {
        $c = $this->database($backend); $f = new TaskFixture($c); $f->create(); $f->issue(confirmation: true, count: 2);
        $f->supply([TaskFixture::KEY_1 => self::accept('partial')]);
        $f->commands->execute(new \Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput('scope', TaskFixture::ID, 'producer', 3, TaskFixture::KEY_1));
        $f = new TaskFixture($this->connect($c->getParams())); $f->issue(expectedVersion: 4);
        self::assertSame([TaskFixture::KEY_3], array_keys($f->get()->get('state')->get('input')->properties()));
        $before = $c->fetchOne('SELECT payload FROM fight_tasks');
        $f->supply([TaskFixture::KEY_1 => false, TaskFixture::KEY_2 => false]);
        self::assertSame($before, $c->fetchOne('SELECT payload FROM fight_tasks'));
        try { $f->supply([TaskFixture::KEY_3 => self::accept(str_repeat('é', 17000))]); self::fail('All encoded bytes are bounded.'); }
        catch (\Fight\Common\Domain\Exception\DomainException) { self::assertSame($before, $c->fetchOne('SELECT payload FROM fight_tasks')); }
        $f->now += 1000; $f->supply([TaskFixture::KEY_3 => self::accept('expired')]);
        self::assertTrue($f->observe()->get('groups')->get(TaskFixture::KEY_3)->get('expired'));
        self::assertSame([], $f->observe()->get('groups')->get(TaskFixture::KEY_3)->get('answers')->properties());
        $f->commands->execute(new \Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput('scope', TaskFixture::ID, 'producer', 5, TaskFixture::KEY_3));
        // Seed the documented near-exhaustion value; direct units additionally execute all 1,024 rounds.
        $data = $f->store->read('scope', TaskFixture::ID)->toArray(); $data['inputs'] = $data['inputs']->with('issued', 1023);
        $f->store->commitTransactional(fn () => $f->store->save(\Fight\Common\Domain\Mcp\Task\Task::fromArray($data)));
        $f->issue(expectedVersion: 6); $last = TaskFixture::KEY_PREFIX.'1024';
        self::assertSame([$last], array_keys($f->get()->get('state')->get('input')->properties()));
        $f->commands->execute(new \Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput('scope', TaskFixture::ID, 'producer', 7, $last));
        $fresh = new TaskFixture($this->connect($c->getParams())); $before = $c->fetchOne('SELECT payload FROM fight_tasks');
        try { $fresh->issue(expectedVersion: 8); self::fail('Lifetime exhaustion cannot wrap after payload retirement.'); }
        catch (TaskConflict) { self::assertSame($before, $c->fetchOne('SELECT payload FROM fight_tasks')); }
        self::assertSame([], $fresh->observe()->get('groups')->properties());
    }

    #[DataProvider('races')]
    public function test_that_independent_processes_admit_once_and_preserve_confirmation_and_terminal_winners(string $backend, string $mode): void
    {
        $c = $this->database($backend); $f = new TaskFixture($c); $f->create();
        $f->issue(confirmation: in_array($mode, ['refusal', 'partial-refusal', 'acquire'], true), count: $mode === 'partial-refusal' ? 2 : 1);
        if ($mode === 'acquire') { $f->supply([TaskFixture::KEY_1 => self::accept('first-answer')]); }
        $directory = $this->directory(); $config = $directory.'/config.json';
        file_put_contents($config, json_encode(['params' => $c->getParams(), 'mode' => $mode], JSON_THROW_ON_ERROR));
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
            $fresh = new TaskFixture($this->connect($c->getParams())); $group = $fresh->observe()->get('groups')->get(TaskFixture::KEY_1);
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
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            foreach (['nonempty' => 'Waiting for both authorized answers', 'empty' => '', 'null' => null] as $name => $message) {
                yield $backend.'-'.$name => [$backend, $message];
            }
        }
    }

    public static function databases(): iterable { foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; } }
    public static function races(): iterable { foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { foreach (['duplicate', 'conflict', 'completed-first', 'failed-first', 'cancelled-first', 'input-first', 'refusal', 'partial-refusal', 'acquire', 'revoked'] as $mode) { yield $backend.'-'.$mode => [$backend, $mode]; } } }
    protected function tearDown(): void { foreach ($this->connections as $c) { $c->close(); } foreach ($this->directories as $d) { $this->removeTemporaryDirectory($d, 'fight-task-input-'); } parent::tearDown(); }
    private static function accept(string $answer): array { return ['action' => 'accept', 'content' => (object) ['answer' => $answer]]; }
    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/input.sqlite']; }
        else { $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Actual disposable server required.'); $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn); }
        $c = $this->connect($params); foreach (array_reverse(DbalTaskSchema::TABLES) as $table) { $c->executeStatement('DROP TABLE IF EXISTS '.$table); } (new DbalTaskSchema())->install($c); return $c;
    }
    private function connect(array $params): Connection { $c = DriverManager::getConnection($params); if ($params['driver'] === 'pdo_sqlite') { $c->executeStatement('PRAGMA busy_timeout = 10000'); } $this->connections[] = $c; return $c; }
    private function checkConnection(array $params): TaskCheckConnection { $c = new TaskCheckConnection($params, DriverManager::getConnection($params)->getDriver()); $this->connections[] = $c; return $c; }
    private function directory(): string { $d = sys_get_temp_dir().'/fight-task-input-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($d, 0700)); $this->directories[] = $d; return $d; }
    private function waitFor(string $path, Process $worker): void { $deadline = microtime(true) + 15; while (!file_exists($path)) { if (!$worker->isRunning() || microtime(true) > $deadline) { self::fail('Barrier unavailable: '.$worker->getErrorOutput().$worker->getOutput()); } usleep(10000); } self::assertFileExists($path); }
}
