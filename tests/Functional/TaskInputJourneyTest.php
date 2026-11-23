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
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class TaskInputJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('routedSelection')]
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

    #[DataProvider('identitySelection')]
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

    public function test_that_routed_confirmation_can_only_be_acquired_by_one_process(): void
    {
        $tables = new DbalTaskTables('TaskState', 'TaskRows');
        $connection = $this->database('mysql', $tables);
        $fixture = new TaskFixture($connection, $tables);
        $fixture->create();
        $fixture->issue(confirmation: true);
        $fixture->supply([TaskFixture::KEY_1 => self::accept('first-answer')]);
        $directory = $this->directory(); $config = $directory.'/config.json';
        file_put_contents($config, json_encode(['params' => $connection->getParams(), 'mode' => 'acquire', 'stateTable' => $tables->stateTable()->name(), 'taskTable' => $tables->taskTable()->name()], JSON_THROW_ON_ERROR));
        $first = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/input-worker.php', $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/input-worker.php', $config, 'second'], timeout: 25);
        try {
            $first->start(); $this->waitFor($directory.'/first-precommit', $first);
            $second->start(); $this->waitFor($directory.'/second-started', $second); usleep(120000);
            self::assertTrue($second->isRunning()); self::assertFileDoesNotExist($directory.'/second-admitted');
            file_put_contents($directory.'/release', 'yes');
            self::assertSame(0, $first->wait(), $first->getErrorOutput()); self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $one = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR); $two = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('committed', $one['status']); self::assertSame(1, $one['events']);
            self::assertSame(TaskConflict::class, $two['status']);
            self::assertSame(0, $two['events']);
            $fresh = new TaskFixture($this->connect($connection->getParams()), $tables);
            $group = $fresh->observe()->get('groups')->get(TaskFixture::KEY_1);
            self::assertSame('working', $fresh->get()->get('state')->get('status'));
            self::assertSame('first-answer', $group->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer'));
            self::assertTrue($group->get('consumed'));
        } finally { file_put_contents($directory.'/release', 'yes'); $first->stop(); $second->stop(); }
    }

    public static function routedSelection(): iterable
    {
        yield 'sqlite-configured' => ['sqlite', new DbalTaskTables('TaskState', 'TaskRows')];
    }

    public static function identitySelection(): iterable
    {
        yield 'mysql-configured' => ['mysql', new DbalTaskTables('TaskState', 'TaskRows')];
    }
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
    private function directory(): string { $temporaryDirectory = sys_get_temp_dir().'/fight-task-input-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($temporaryDirectory, 0700)); $this->directories[] = $temporaryDirectory; return $temporaryDirectory; }
    private function waitFor(string $path, Process $worker): void { $deadline = microtime(true) + 15; while (!file_exists($path)) { if (!$worker->isRunning() || microtime(true) > $deadline) { self::fail('Barrier unavailable: '.$worker->getErrorOutput().$worker->getOutput()); } usleep(10000); } self::assertFileExists($path); }
}
