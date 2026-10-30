<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestMetadata;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Application\Mcp\Task\McpTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskCheckConnection;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class TaskCancellationJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('outcomes')]
    public function test_that_real_routed_intent_reconstructs_in_another_process_and_consumer_reports_only_actual_outcome(string $backend, string $outcome): void
    {
        $c = $this->database($backend); $f = new TaskFixture($c); $f->create(10000, 25); $f->issue(confirmation: true);
        $facts = ['admitted' => false, 'observed' => false, 'stopping_attempted' => false, 'effect' => 'unknown'];
        $method = new McpTaskCancellation($f->commands, 'scope', 'caller');
        self::assertSame(['resultType' => 'complete'], $method->handle(new McpRequest('cancel-1', 'tasks/cancel', ['taskId' => TaskFixture::ID], McpRequestMetadata::fromObject(StrictJson::fromObject([
            McpRequestMetadata::PROTOCOL_VERSION_KEY => '2026-07-28', McpRequestMetadata::CLIENT_CAPABILITIES_KEY => (object) ['extensions' => (object) ['io.modelcontextprotocol/tasks' => (object) []]]
        ]))))->toArray());
        $facts['admitted'] = true;
        $config = $this->config($c, 'observe', 'observe', 3);
        $worker = $this->worker($config, 'observer'); $worker->mustRun(); $observed = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotSame(getmypid(), $observed['pid']); self::assertSame(3, $observed['observation']['cancellation']['version']);
        $facts['observed'] = true;
        self::assertFalse($facts['stopping_attempted']); self::assertSame('input_required', $f->get()->get('state')->get('status'));
        $fresh = new TaskFixture($this->connect($c->getParams()));
        $fresh->supply([TaskFixture::KEY_1 => self::accept()]); $fresh->acquire();
        $continuation = $fresh->observe(); self::assertNotNull($continuation->get('cancellation'));
        self::assertTrue($continuation->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
        $attempts = 0;
        $stop = static function () use ($c, $outcome, &$attempts): string {
            self::assertFalse($c->isTransactionActive(), 'Consumer stopping must not span admission');
            ++$attempts;
            if ($outcome === 'failed') { throw new RuntimeException('Consumer effect outcome uncertain'); }
            return 'stopped';
        };
        if ($outcome === 'completed') { $facts['effect'] = 'completed-despite-intent'; }
        else {
            $facts['stopping_attempted'] = true;
            try { $facts['effect'] = $stop(); } catch (RuntimeException) { $facts['effect'] = 'uncertain'; }
        }
        self::assertSame($outcome === 'completed' ? 0 : 1, $attempts);
        self::assertSame($outcome === 'cancelled' ? 'stopped' : ($outcome === 'failed' ? 'uncertain' : 'completed-despite-intent'), $facts['effect']);
        // Consumer fixture owns this decision outside admission; Common has called no worker or external effect.
        $state = match ($outcome) { 'cancelled' => TaskState::create('cancelled'), 'failed' => TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), default => TaskFixture::completed() };
        $fresh->record($state, 5);
        $fresh = new TaskFixture($this->connect($c->getParams())); $before = $c->fetchOne('SELECT payload FROM fight_tasks');
        self::assertSame($outcome, $fresh->get()->get('state')->get('status')); $fresh->cancel(); $fresh->supply([TaskFixture::KEY_1 => false]);
        self::assertSame($before, $c->fetchOne('SELECT payload FROM fight_tasks'));
        self::assertSame(3, $fresh->cancellation()->get('cancellation')->get('version'));
        try { $fresh->acquire(); self::fail('No repeat continuation'); } catch (TaskConflict) { self::assertTrue($fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed')); }
        self::assertTrue($facts['admitted']); self::assertTrue($facts['observed']); self::assertNotSame('unknown', $facts['effect']);
        $fresh->cancelAllowed = false;
        try { $fresh->cancel(); self::fail('Terminal repeat reauthorizes'); } catch (TaskRejected) { self::assertSame($before, $c->fetchOne('SELECT payload FROM fight_tasks')); }
        $fresh->cancelObserveAllowed = false; self::assertNull($fresh->cancellation()); self::assertNull($fresh->observe());
        $fresh->cancelObserveAllowed = true; $fresh->now += 10000; self::assertNull($fresh->cancellation());
        $fresh->cancelAllowed = true;
        try { $fresh->cancel(); self::fail('Expiry is not cancellation'); } catch (TaskRejected) { self::assertSame($before, $c->fetchOne('SELECT payload FROM fight_tasks')); }
        $fresh->remove(); self::assertNull($fresh->cancellation());
        try { $fresh->cancel(); self::fail('No resurrection'); } catch (TaskRejected) { self::assertSame(0, (int) $c->fetchOne('SELECT COUNT(*) FROM fight_tasks')); }
    }

    #[DataProvider('databases')]
    public function test_that_real_rollback_commit_response_loss_and_postcommit_faults_preserve_truthful_intent_and_caller_work(string $backend): void
    {
        $c = $this->database($backend); $f = new TaskFixture($c); $f->create(); $f->issue(confirmation: true); $params = $c->getParams();
        $before = $c->fetchOne('SELECT payload FROM fight_tasks');
        $check = $this->checked($params); $candidate = new TaskFixture($check);
        $check->beforeCommit = static fn () => throw new RuntimeException('Before commit');
        try { $candidate->cancel(); self::fail('Commit failure cannot acknowledge'); } catch (TaskStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); }
        self::assertSame($before, $this->connect($params)->fetchOne('SELECT payload FROM fight_tasks'));
        if ($backend === 'sqlite') { $c->executeStatement("CREATE TRIGGER fail_cancel BEFORE INSERT ON fight_tasks BEGIN SELECT RAISE(ABORT, 'private'); END"); }
        else { $c->executeStatement("ALTER TABLE fight_tasks ADD CONSTRAINT fail_cancel CHECK (payload LIKE '%cancellation_requested_at\":null%')"); }
        try { $f->cancel(); self::fail('Real storage failure must roll back'); } catch (TaskStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); }
        self::assertSame($before, $this->connect($params)->fetchOne('SELECT payload FROM fight_tasks'));
        if ($backend === 'sqlite') { $c->executeStatement('DROP TRIGGER fail_cancel'); }
        elseif ($backend === 'mysql') { $c->executeStatement('ALTER TABLE fight_tasks DROP CHECK fail_cancel'); }
        else { $c->executeStatement('ALTER TABLE fight_tasks DROP CONSTRAINT fail_cancel'); }
        $check = $this->checked($params); $candidate = new TaskFixture($check);
        $check->afterCommit = static fn () => throw new RuntimeException('Lost commit response');
        try { $candidate->cancel(); self::fail('Uncertain commit cannot acknowledge'); } catch (TaskStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); self::assertTrue($candidate->store->isClosed()); }
        $fresh = new TaskFixture($this->connect($params)); self::assertNotNull($fresh->cancellation()->get('cancellation'));
        $committed = $c->fetchOne('SELECT payload FROM fight_tasks'); $fresh->cancel(); self::assertSame($committed, $c->fetchOne('SELECT payload FROM fight_tasks'));
        $fresh->create(id: str_repeat('b', 32)); $calls = 0;
        $fresh->events->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$calls): void { ++$calls; throw new RuntimeException('Private postcommit callback'); });
        try { $fresh->cancel(id: str_repeat('b', 32)); self::fail('Postcommit dispatch is not rollback'); } catch (CommittedTaskDispatchFailed) { self::assertNotNull((new TaskFixture($this->connect($params)))->cancellation(id: str_repeat('b', 32))->get('cancellation')); }
        $fresh->cancel(id: str_repeat('b', 32)); self::assertSame(1, $calls);
        $c->executeStatement('CREATE TABLE cancellation_caller_work (id INTEGER)'); $c->beginTransaction(); $c->insert('cancellation_caller_work', ['id' => 7]);
        try { $f->cancel(); self::fail('No savepoint acknowledgment'); } catch (LogicException) { self::assertTrue($c->isTransactionActive()); self::assertSame(7, (int) $c->fetchOne('SELECT id FROM cancellation_caller_work')); }
        $c->commit(); self::assertSame(7, (int) $this->connect($params)->fetchOne('SELECT id FROM cancellation_caller_work')); $c->executeStatement('DROP TABLE cancellation_caller_work');
    }

    #[DataProvider('races')]
    public function test_that_independent_contenders_preserve_intent_answers_consumption_and_terminal_winners_in_both_lock_orders(string $backend, string $firstOp, string $secondOp, bool $revoke): void
    {
        $c = $this->database($backend); $f = new TaskFixture($c); $f->create(); $f->issue(confirmation: true);
        if (in_array('acquire', [$firstOp, $secondOp], true)) { $f->supply([TaskFixture::KEY_1 => self::accept()]); }
        $version = $f->get()->get('version'); $config = $this->config($c, $firstOp, $secondOp, $version); $directory = dirname($config);
        $one = $this->worker($config, 'first'); $two = $this->worker($config, 'second');
        try {
            $one->start(); $this->waitFor($directory.'/first-precommit', $one);
            $two->start(); $this->waitFor($directory.'/second-started', $two); usleep(120000);
            self::assertTrue($two->isRunning()); self::assertFileDoesNotExist($directory.'/second-admitted');
            if ($revoke) { file_put_contents($directory.'/revoked', 'yes'); }
            file_put_contents($directory.'/release', 'yes');
            self::assertSame(0, $one->wait(), $one->getErrorOutput()); self::assertSame(0, $two->wait(), $two->getErrorOutput());
            $a = json_decode($one->getOutput(), true, flags: JSON_THROW_ON_ERROR); $b = json_decode($two->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertNotSame($a['pid'], $b['pid']); self::assertNotSame(getmypid(), $a['pid']); self::assertSame('committed', $a['status']);
            $terminal = in_array($firstOp, ['completed', 'failed', 'cancelled'], true);
            $stale = $firstOp === 'cancel' && in_array($secondOp, ['completed', 'failed', 'cancelled', 'issue'], true);
            self::assertSame($revoke ? TaskRejected::class : ($stale ? TaskConflict::class : 'committed'), $b['status']);
            self::assertSame(1, $a['events']); self::assertSame(($revoke || $stale || $terminal || $secondOp === 'cancel' && $firstOp === 'cancel') ? 0 : 1, $b['events']);
            $fresh = new TaskFixture($this->connect($c->getParams())); $observed = $fresh->observe(); $group = $observed->get('groups')->get(TaskFixture::KEY_1);
            $resumed = in_array('acquire', [$firstOp, $secondOp], true) || $firstOp === 'supply' || (!$revoke && $secondOp === 'supply');
            self::assertSame($terminal ? $firstOp : ($resumed ? 'working' : 'input_required'), $fresh->get()->get('state')->get('status'));
            $intent = $fresh->cancellation()->get('cancellation');
            if ($terminal) { self::assertNull($intent); }
            else { self::assertNotNull($intent); self::assertSame($intent->toString(), $observed->get('cancellation')->toString()); }
            $answered = in_array('acquire', [$firstOp, $secondOp], true) || in_array('supply', [$firstOp, $secondOp], true) && !($revoke && $secondOp === 'supply');
            self::assertSame($answered ? 1 : 0, count($group->get('answers')->properties()));
            if ($answered) { self::assertSame('accepted', $group->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer')); }
            self::assertSame(in_array('acquire', [$firstOp, $secondOp], true) && !($revoke && $secondOp === 'acquire'), $group->get('consumed'));
            if ($stale && $secondOp !== 'issue') {
                // An explicit fresh producer decision may report the actual winner despite prior intent.
                $state = match ($secondOp) { 'completed' => TaskFixture::completed(), 'failed' => TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), default => TaskState::create('cancelled') };
                $fresh->record($state, $fresh->get()->get('version')); self::assertSame($secondOp, $fresh->get()->get('state')->get('status'));
                self::assertSame($intent->toString(), $fresh->cancellation()->get('cancellation')->toString());
            }
            if ($group->get('consumed')) { try { $fresh->acquire(); self::fail('Consumption survives cancellation'); } catch (TaskConflict) { self::assertTrue($fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed')); } }
        } finally { file_put_contents($directory.'/release', 'yes'); $one->stop(); $two->stop(); }
    }

    #[DataProvider('inputTerminals')]
    public function test_that_actual_input_and_confirmation_racing_terminal_producers_cannot_clear_prior_intent_or_reopen_the_winner(string $backend, string $input, string $terminal, bool $terminalFirst): void
    {
        $c = $this->database($backend); $f = new TaskFixture($c); $f->create(); $f->issue(confirmation: true); $f->cancel();
        $intent = $f->cancellation()->get('cancellation')->toString();
        if ($input === 'acquire') { $f->supply([TaskFixture::KEY_1 => self::accept()]); }
        $version = $f->get()->get('version');
        $config = $this->config($c, $terminalFirst ? $terminal : $input, $terminalFirst ? $input : $terminal, $version);
        $directory = dirname($config); $one = $this->worker($config, 'first'); $two = $this->worker($config, 'second');
        try {
            $one->start(); $this->waitFor($directory.'/first-precommit', $one);
            $two->start(); $this->waitFor($directory.'/second-started', $two); usleep(120000);
            self::assertTrue($two->isRunning()); self::assertFileDoesNotExist($directory.'/second-admitted');
            file_put_contents($directory.'/release', 'yes');
            self::assertSame(0, $one->wait(), $one->getErrorOutput()); self::assertSame(0, $two->wait(), $two->getErrorOutput());
            $a = json_decode($one->getOutput(), true, flags: JSON_THROW_ON_ERROR); $b = json_decode($two->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertNotSame($a['pid'], $b['pid']); self::assertSame('committed', $a['status']); self::assertSame(1, $a['events']);
            self::assertSame($terminalFirst && $input === 'supply' ? 'committed' : TaskConflict::class, $b['status']); self::assertSame(0, $b['events']);
            $fresh = new TaskFixture($this->connect($c->getParams()));
            self::assertSame($terminalFirst ? $terminal : 'working', $fresh->get()->get('state')->get('status'));
            $view = $fresh->observe(); $group = $view->get('groups')->get(TaskFixture::KEY_1);
            self::assertSame($intent, $view->get('cancellation')->toString()); self::assertSame($intent, $fresh->cancellation()->get('cancellation')->toString());
            self::assertSame($input === 'acquire' || !$terminalFirst ? 1 : 0, count($group->get('answers')->properties()));
            self::assertSame($input === 'acquire' && !$terminalFirst, $group->get('consumed'));
            if ($input === 'acquire' || !$terminalFirst) { self::assertSame('accepted', $group->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer')); }
            if (!$terminalFirst) {
                $state = match ($terminal) { 'completed' => TaskFixture::completed(), 'failed' => TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), default => TaskState::create('cancelled') };
                $fresh->record($state, $fresh->get()->get('version'));
                self::assertSame($terminal, $fresh->get()->get('state')->get('status'));
                self::assertSame($intent, $fresh->observe()->get('cancellation')->toString());
                self::assertSame($input === 'acquire', $fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
            }
        } finally { file_put_contents($directory.'/release', 'yes'); $one->stop(); $two->stop(); }
    }

    public static function inputTerminals(): iterable
    {
        foreach (self::databases() as [$db]) { foreach (['supply', 'acquire'] as $input) { foreach (['completed', 'failed', 'cancelled'] as $terminal) { foreach ([true, false] as $first) { yield $db.'-'.$input.'-'.$terminal.'-'.($first ? 'terminal-first' : 'input-first') => [$db, $input, $terminal, $first]; } } } }
    }

    public static function databases(): iterable { foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; } }
    public static function outcomes(): iterable { foreach (self::databases() as [$db]) { foreach (['completed', 'failed', 'cancelled'] as $outcome) { yield $db.'-'.$outcome => [$db, $outcome]; } } }
    public static function races(): iterable
    {
        foreach (self::databases() as [$db]) {
            foreach (['supply', 'acquire', 'issue', 'completed', 'failed', 'cancelled', 'cancel'] as $op) {
                yield $db.'-cancel-'.$op => [$db, 'cancel', $op, false];
                if ($op !== 'cancel') { yield $db.'-'.$op.'-cancel' => [$db, $op, 'cancel', false]; }
            }
            foreach (['cancel', 'supply', 'acquire'] as $op) { yield $db.'-revoked-'.$op => [$db, 'cancel', $op, true]; }
        }
    }
    protected function tearDown(): void { foreach ($this->connections as $c) { $c->close(); } foreach ($this->directories as $d) { $this->removeTemporaryDirectory($d, 'fight-task-cancellation-'); } parent::tearDown(); }
    private static function accept(): array { return ['action' => 'accept', 'content' => (object) ['answer' => 'accepted']]; }
    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/cancellation.sqlite']; }
        else { $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Actual disposable server required'); $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn); }
        $c = $this->connect($params); foreach (array_reverse(DbalTaskSchema::TABLES) as $table) { $c->executeStatement('DROP TABLE IF EXISTS '.$table); } (new DbalTaskSchema())->install($c); return $c;
    }
    private function connect(array $params): Connection { $c = DriverManager::getConnection($params); if ($params['driver'] === 'pdo_sqlite') { $c->executeStatement('PRAGMA busy_timeout = 10000'); } $this->connections[] = $c; return $c; }
    private function checked(array $params): TaskCheckConnection { $c = new TaskCheckConnection($params, DriverManager::getConnection($params)->getDriver()); $this->connections[] = $c; return $c; }
    private function directory(): string { $d = sys_get_temp_dir().'/fight-task-cancellation-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($d, 0700)); $this->directories[] = $d; return $d; }
    private function config(Connection $c, string $first, string $second, int $version): string { $path = $this->directory().'/config.json'; file_put_contents($path, json_encode(['params' => $c->getParams(), 'first' => $first, 'second' => $second, 'observer' => 'observe', 'version' => $version], JSON_THROW_ON_ERROR)); return $path; }
    private function worker(string $config, string $role): Process { return new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/cancellation-worker.php', $config, $role], timeout: 25); }
    private function waitFor(string $path, Process $worker): void { $deadline = microtime(true) + 15; while (!file_exists($path)) { if (!$worker->isRunning() || microtime(true) > $deadline) { self::fail('Barrier unavailable: '.$worker->getErrorOutput().$worker->getOutput()); } usleep(10000); } self::assertFileExists($path); }
}
