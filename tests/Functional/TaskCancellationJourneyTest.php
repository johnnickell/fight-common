<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Application\Mcp\Protocol\McpRequest;
use Fight\Common\Application\Mcp\Protocol\McpRequestMetadata;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\McpTaskCancellation;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
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
    public function test_that_real_routed_intent_reconstructs_in_another_process_and_consumer_reports_only_actual_outcome(string $backend, string $outcome, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend, $tables); $fixture = new TaskFixture($connection, $tables); $fixture->create(10000, 25); $fixture->issue(confirmation: true);
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform());
        $facts = ['admitted' => false, 'observed' => false, 'stopping_attempted' => false, 'effect' => 'unknown'];
        $method = new McpTaskCancellation($fixture->commandBus, 'scope', 'caller');
        self::assertSame(['resultType' => 'complete'], $method->handle(new McpRequest('cancel-1', 'tasks/cancel', ['taskId' => TaskFixture::ID], McpRequestMetadata::fromObject(StrictJson::fromObject([
            McpRequestMetadata::PROTOCOL_VERSION_KEY => '2026-07-28', McpRequestMetadata::CLIENT_CAPABILITIES_KEY => (object) ['extensions' => (object) ['io.modelcontextprotocol/tasks' => (object) []]]
        ]))))->toArray());
        $facts['admitted'] = true;
        $config = $this->config($connection, 'observe', 'observe', 3, $tables);
        $worker = $this->worker($config, 'observer'); $worker->mustRun(); $observed = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotSame(getmypid(), $observed['pid']); self::assertSame(3, $observed['observation']['cancellation']['version']);
        $facts['observed'] = true;
        self::assertFalse($facts['stopping_attempted']); self::assertSame('input_required', $fixture->get()->get('state')->get('status'));
        $fresh = new TaskFixture($this->connect($connection->getParams()), $tables);
        $fresh->supply([TaskFixture::KEY_1 => self::accept()]); $fresh->acquire();
        $continuation = $fresh->observe(); self::assertNotNull($continuation->get('cancellation'));
        self::assertTrue($continuation->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
        $attempts = 0;
        $stop = static function () use ($connection, $outcome, &$attempts): string {
            self::assertFalse($connection->isTransactionActive(), 'Consumer stopping must not span admission');
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
        $fresh = new TaskFixture($this->connect($connection->getParams()), $tables); $before = $connection->fetchOne('SELECT payload FROM '.$taskTable);
        self::assertSame($outcome, $fresh->get()->get('state')->get('status')); $fresh->cancel(); $fresh->supply([TaskFixture::KEY_1 => false]);
        self::assertSame($before, $connection->fetchOne('SELECT payload FROM '.$taskTable));
        self::assertSame(3, $fresh->cancellation()->get('cancellation')->get('version'));
        try { $fresh->acquire(); self::fail('No repeat continuation'); } catch (TaskConflict) { self::assertTrue($fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed')); }
        self::assertTrue($facts['admitted']); self::assertTrue($facts['observed']); self::assertNotSame('unknown', $facts['effect']);
        $fresh->cancelAllowed = false;
        try { $fresh->cancel(); self::fail('Terminal repeat reauthorizes'); } catch (TaskRejected) { self::assertSame($before, $connection->fetchOne('SELECT payload FROM '.$taskTable)); }
        $fresh->cancelObserveAllowed = false; self::assertNull($fresh->cancellation()); self::assertNull($fresh->observe());
        $fresh->cancelObserveAllowed = true; $fresh->now += 10000; self::assertNull($fresh->cancellation());
        $fresh->cancelAllowed = true;
        try { $fresh->cancel(); self::fail('Expiry is not cancellation'); } catch (TaskRejected) { self::assertSame($before, $connection->fetchOne('SELECT payload FROM '.$taskTable)); }
        $fresh->remove(); self::assertNull($fresh->cancellation());
        try { $fresh->cancel(); self::fail('No resurrection'); } catch (TaskRejected) { self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$taskTable)); }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend => [$backend, new DbalTaskTables()];
            yield $backend.'-configured' => [$backend, new DbalTaskTables('TaskState', 'TaskRows')];
        }
    }

    public static function outcomes(): iterable
    {
        $outcomes = [
            'sqlite-configured' => 'failed',
            'mysql' => 'cancelled',
            'postgresql' => 'completed',
        ];
        foreach (self::databases() as $label => [$backend, $tables]) {
            if (isset($outcomes[$label])) {
                $outcome = $outcomes[$label];
                yield $label.'-'.$outcome => [$backend, $outcome, $tables];
            }
        }
    }

    protected function tearDown(): void { foreach ($this->connections as $connection) { $connection->close(); } foreach ($this->directories as $temporaryDirectory) { $this->removeTemporaryDirectory($temporaryDirectory, 'fight-task-cancellation-'); } parent::tearDown(); }
    private static function accept(): array { return ['action' => 'accept', 'content' => (object) ['answer' => 'accepted']]; }
    private function database(string $backend, DbalTaskTables $tables): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/cancellation.sqlite']; }
        else { $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Actual disposable server required'); $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn); }
        $connection = $this->connect($params);
        foreach (array_unique([...array_reverse(DbalTaskSchema::TABLES), $tables->taskTable()->name(), $tables->stateTable()->name()]) as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$connection->getDatabasePlatform()->quoteSingleIdentifier($table));
        }
        (new DbalTaskSchema($tables))->install($connection); return $connection;
    }
    private function connect(array $params): Connection { $connection = DriverManager::getConnection($params); if ($params['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); } $this->connections[] = $connection; return $connection; }
    private function directory(): string { $temporaryDirectory = sys_get_temp_dir().'/fight-task-cancellation-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($temporaryDirectory, 0700)); $this->directories[] = $temporaryDirectory; return $temporaryDirectory; }
    private function config(Connection $connection, string $first, string $second, int $version, DbalTaskTables $tables): string { $path = $this->directory().'/config.json'; file_put_contents($path, json_encode(['params' => $connection->getParams(), 'first' => $first, 'second' => $second, 'observer' => 'observe', 'version' => $version, 'stateTable' => $tables->stateTable()->name(), 'taskTable' => $tables->taskTable()->name()], JSON_THROW_ON_ERROR)); return $path; }
    private function worker(string $config, string $role): Process { return new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/cancellation-worker.php', $config, $role], timeout: 25); }
}
