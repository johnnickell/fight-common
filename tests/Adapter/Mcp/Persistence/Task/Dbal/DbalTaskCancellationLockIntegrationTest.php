<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Task\Dbal;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Domain\Mcp\Task\Command\AcquireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskInput;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Mcp\Task\TaskCancellationOperations;
use Fight\Test\Common\TestCase\Mcp\Task\TaskCheckConnection;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class DbalTaskCancellationLockIntegrationTest extends UnitTestCase
{
    #[DataProvider('lockOrders')]
    public function test_that_task_cancellation_and_input_recheck_admission_under_the_writer_lock(
        string $backend,
        bool $configured,
        string $first,
        string $second,
        bool $revoke
    ): void {
        $directory = sys_get_temp_dir().'/fight-task-cancel-lock-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $params = $this->parameters($backend, $directory);
        $tables = $configured ? new DbalTaskTables('TaskState', 'TaskRows') : new DbalTaskTables();
        $setup = DriverManager::getConnection($params);
        $observer = DriverManager::getConnection($params);
        $connection = new TaskCheckConnection($params, $setup->getDriver());
        $taskTable = $tables->taskTable()->quoted($setup->getDatabasePlatform());
        $stateTable = $tables->stateTable()->quoted($setup->getDatabasePlatform());
        $configPath = $directory.'/config.json';
        file_put_contents($configPath, json_encode([
            'params'    => $params,
            'tables'    => [$tables->stateTable()->name(), $tables->taskTable()->name()],
            'operation' => $second
        ], JSON_THROW_ON_ERROR));
        $workerPath = realpath(__DIR__.'/../../../../../TestCase/Mcp/Task/cancellation-contender.php');
        self::assertIsString($workerPath);
        $worker = new Process([PHP_BINARY, $workerPath, $configPath], timeout: 25);
        $inputQuery = QueryMessage::create(new GetTaskInput('scope', TaskFixture::id(), 'caller'));
        $cancelQuery = QueryMessage::create(new GetTaskCancellation('scope', TaskFixture::id(), 'caller'));
        $acquire = CommandMessage::create(new AcquireTaskInput('scope', TaskFixture::id(), 'caller', TaskFixture::KEY_1));
        $cancel = CommandMessage::create(new RequestTaskCancellation('scope', TaskFixture::id(), 'caller'));

        try {
            $setup->executeStatement("DROP TABLE IF EXISTS $taskTable");
            $setup->executeStatement("DROP TABLE IF EXISTS $stateTable");
            (new DbalTaskSchema($tables))->install($setup);
            $seed = new TaskFixture($setup, $tables);
            $seed->create();
            $seed->issue(confirmation: true);
            $answered = in_array('acquire', [$first, $second], true);
            if ($answered) {
                $seed->supply([TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'accepted']]]);
            }
            $before = $observer->fetchOne("SELECT payload FROM $taskTable");
            $writer = new TaskFixture($connection, $tables);
            $events = 0;
            $writer->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$events): void {
                ++$events;
            });
            $connection->beforeCommit = function () use ($directory, $worker, $revoke, $observer, $taskTable, $before): void {
                $worker->start();
                $this->waitForContender($worker, $directory.'/started');
                usleep(120000);
                self::assertTrue($worker->isRunning(), $worker->getErrorOutput());
                self::assertFileDoesNotExist($directory.'/admitted');
                self::assertSame($before, $observer->fetchOne("SELECT payload FROM $taskTable"));
                if ($revoke) {
                    file_put_contents($directory.'/revoked', 'yes');
                }
            };
            if ($first === 'cancel') {
                $writer->requestTaskCancellationHandler->handle($cancel);
            } else {
                $writer->acquireTaskInputHandler->handle($acquire);
            }

            self::assertSame(0, $worker->wait(), $worker->getErrorOutput());
            self::assertFileExists($directory.'/admitted');
            self::assertSame([
                'outcome' => $revoke ? TaskRejected::class : 'committed',
                'events'  => $revoke ? 0 : 1,
                'active'  => false
            ], json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR));
            self::assertSame(1, $events);
            $reader = new TaskFixture($observer, $tables);
            $view = $reader->getTaskInputHandler->handle($inputQuery);
            $intent = $reader->getTaskCancellationHandler->handle($cancelQuery)->get('cancellation');
            $group = $view->get('groups')->get(TaskFixture::KEY_1);
            $intentVersion = !$answered ? 3 : ($first === 'cancel' ? 4 : 5);
            self::assertSame($intentVersion, $intent->get('version'));
            self::assertSame($intent->toString(), $view->get('cancellation')->toString());
            self::assertSame($answered ? 1 : 0, count($group->get('answers')->properties()));
            self::assertSame($answered && !$revoke, $group->get('consumed'));
            self::assertSame($answered ? 'working' : 'input_required', $reader->committedTaskReader->read('scope', TaskFixture::id())->state()->status());
            if ($answered) {
                self::assertSame('accepted', $group->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer'));
            }
            if ($group->get('consumed')) {
                try {
                    $reader->acquireTaskInputHandler->handle($acquire);
                    self::fail('The independent writer must not consume a used input again.');
                } catch (TaskConflict) {
                    self::assertTrue($reader->getTaskInputHandler->handle($inputQuery)->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
                }
            }
        } finally {
            $worker->stop();
            $connection->close();
            $observer->close();
            $setup->executeStatement("DROP TABLE IF EXISTS $taskTable");
            $setup->executeStatement("DROP TABLE IF EXISTS $stateTable");
            $setup->close();
            $this->removeTemporaryDirectory($directory, 'fight-task-cancel-lock-');
        }
    }

    #[DataProvider('terminalOrders')]
    #[DataProvider('cancellationOrders')]
    public function test_that_serialized_handlers_preserve_cancellation_input_and_the_terminal_winner(
        string $backend,
        bool $configured,
        string $first,
        string $second,
        bool $seedIntent
    ): void {
        $directory = sys_get_temp_dir().'/fight-task-cancel-lock-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $params = $this->parameters($backend, $directory);
        $tables = $configured ? new DbalTaskTables('TaskState', 'TaskRows') : new DbalTaskTables();
        $observer = DriverManager::getConnection($params);
        $writerConnection = new TaskCheckConnection($params, $observer->getDriver());
        $taskTable = $tables->taskTable()->quoted($observer->getDatabasePlatform());
        $stateTable = $tables->stateTable()->quoted($observer->getDatabasePlatform());
        $configPath = $directory.'/config.json';
        $workerPath = dirname(__DIR__, 5).'/TestCase/Mcp/Task/cancellation-worker.php';
        $worker = new Process([PHP_BINARY, $workerPath, $configPath, 'second'], timeout: 25);
        try {
            $observer->executeStatement("DROP TABLE IF EXISTS $taskTable");
            $observer->executeStatement("DROP TABLE IF EXISTS $stateTable");
            (new DbalTaskSchema($tables))->install($observer);
            $reader = new TaskFixture($observer, $tables);
            $reader->create();
            $reader->issue(confirmation: true);
            if ($seedIntent) {
                TaskCancellationOperations::invoke($reader, 'cancel', 2);
            }
            $acquires = in_array('acquire', [$first, $second], true);
            if ($acquires) {
                TaskCancellationOperations::invoke($reader, 'supply', 3);
            }
            $id = TaskFixture::id();
            $inputQuery = QueryMessage::create(new GetTaskInput('scope', $id, 'caller'));
            $cancelQuery = QueryMessage::create(new GetTaskCancellation('scope', $id, 'caller'));
            $intent = $reader->getTaskCancellationHandler->handle($cancelQuery)->get('cancellation');
            $before = $observer->fetchOne("SELECT payload FROM $taskTable");
            $version = $reader->committedTaskReader->read('scope', $id)->version();
            file_put_contents($configPath, json_encode([
                'params' => $params,
                'stateTable' => $tables->stateTable()->name(),
                'taskTable' => $tables->taskTable()->name(),
                'second' => $second,
                'version' => $version,
                'direct' => true
            ], JSON_THROW_ON_ERROR));
            $writer = new TaskFixture($writerConnection, $tables);
            $events = 0;
            $writer->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$events): void {
                ++$events;
            });
            $writerConnection->beforeCommit = function () use ($directory, $worker, $observer, $taskTable, $before): void {
                $worker->start();
                $this->waitForContender($worker, $directory.'/second-started');
                usleep(120000);
                self::assertTrue($worker->isRunning(), $worker->getErrorOutput().$worker->getOutput());
                self::assertFileDoesNotExist($directory.'/second-admitted');
                self::assertSame($before, $observer->fetchOne("SELECT payload FROM $taskTable"));
            };
            TaskCancellationOperations::invoke($writer, $first, $version);
            self::assertSame(0, $worker->wait(), $worker->getErrorOutput());
            self::assertFileExists($directory.'/second-admitted');
            $terminalStates = ['completed', 'failed', 'cancelled'];
            $terminalFirst = in_array($first, $terminalStates, true);
            $terminalSecond = in_array($second, $terminalStates, true);
            $conflict = $terminalSecond || $second === 'issue' || ($terminalFirst && $second === 'acquire');
            $secondChanged = !$terminalFirst && !$conflict && $first !== $second;
            self::assertSame([
                'status' => $conflict ? TaskConflict::class : 'committed',
                'events' => (int) $secondChanged,
                'active' => false
            ], json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR));
            self::assertSame(1, $events);
            $committed = $reader->committedTaskReader->read('scope', $id);
            self::assertSame($version + 1 + (int) $secondChanged, $committed->version());
            $answered = $acquires || $first === 'supply' || ($second === 'supply' && $secondChanged);
            self::assertSame($terminalFirst ? $first : ($answered ? 'working' : 'input_required'), $committed->state()->status());
            $view = $reader->getTaskInputHandler->handle($inputQuery);
            $group = $view->get('groups')->get(TaskFixture::KEY_1);
            $observedIntent = $view->get('cancellation');
            $expectedIntentVersion = match (true) {
                $seedIntent => $intent->get('version'),
                $terminalFirst => null,
                $first === 'cancel' => $version + 1,
                default => $version + 2
            };
            self::assertSame($expectedIntentVersion, $observedIntent?->get('version'));
            if ($seedIntent) {
                self::assertSame($intent->toString(), $observedIntent->toString());
            }
            self::assertSame($observedIntent?->toString(), $reader->getTaskCancellationHandler->handle($cancelQuery)->get('cancellation')?->toString());
            self::assertCount($answered ? 1 : 0, $group->get('answers')->properties());
            self::assertSame($first === 'acquire', $group->get('consumed'));
            if ($answered) {
                self::assertSame('accepted', $group->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer'));
            }
            if ($terminalSecond) {
                TaskCancellationOperations::invoke($reader, $second, $committed->version());
                self::assertSame($second, $reader->committedTaskReader->read('scope', $id)->state()->status());
                $final = $reader->getTaskInputHandler->handle($inputQuery);
                self::assertSame($observedIntent->toString(), $final->get('cancellation')->toString());
                self::assertSame($first === 'acquire', $final->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
            }
        } finally {
            $worker->stop();
            $writerConnection->close();
            try {
                $observer->executeStatement("DROP TABLE IF EXISTS $taskTable");
                $observer->executeStatement("DROP TABLE IF EXISTS $stateTable");
            } finally {
                $observer->close();
                $this->removeTemporaryDirectory($directory, 'fight-task-cancel-lock-');
            }
        }
    }

    public static function terminalOrders(): iterable
    {
        foreach (['sqlite' => 'completed', 'mysql' => 'failed', 'postgresql' => 'cancelled'] as $backend => $terminal) {
            foreach (['supply', 'acquire'] as $input) {
                foreach ([true, false] as $terminalFirst) {
                    $configured = $terminalFirst === ($input === 'acquire');
                    $order = $terminalFirst ? 'terminal-first' : 'input-first';
                    yield $backend.'-'.$input.'-'.$order => [$backend, $configured, $terminalFirst ? $terminal : $input, $terminalFirst ? $input : $terminal, true];
                }
            }
        }
    }

    public static function cancellationOrders(): iterable
    {
        $orders = [
            'sqlite' => [[false, 'cancel', 'cancel'], [false, 'cancel', 'supply'], [false, 'issue', 'cancel'], [true, 'supply', 'cancel'], [true, 'cancel', 'issue']],
            'mysql' => [[false, 'cancel', 'completed'], [true, 'completed', 'cancel']],
            'postgresql' => [[false, 'cancel', 'failed'], [false, 'cancelled', 'cancel'], [true, 'failed', 'cancel'], [true, 'cancel', 'cancelled']]
        ];
        foreach ($orders as $backend => $lanes) {
            foreach ($lanes as [$configured, $first, $second]) {
                yield $backend.'-'.$first.'-'.$second => [$backend, $configured, $first, $second, false];
            }
        }
    }

    public static function lockOrders(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend.'-cancel-first' => [$backend, false, 'cancel', 'acquire', false];
            yield $backend.'-acquire-first' => [$backend, true, 'acquire', 'cancel', false];
        }
        yield 'sqlite-revoked-cancel' => ['sqlite', false, 'cancel', 'cancel', true];
        yield 'mysql-revoked-supply' => ['mysql', true, 'cancel', 'supply', true];
        yield 'postgresql-revoked-acquire' => ['postgresql', true, 'cancel', 'acquire', true];
    }

    private function parameters(string $backend, string $directory): array
    {
        if ($backend === 'sqlite') {
            return ['driver' => 'pdo_sqlite', 'path' => $directory.'/tasks.sqlite'];
        }

        $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
        self::assertIsString($dsn, 'Actual disposable server required.');

        return (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
    }

    private function waitForContender(Process $worker, string $path): void
    {
        $deadline = microtime(true) + 15;
        while (!is_file($path)) {
            if (!$worker->isRunning() || microtime(true) > $deadline) {
                self::fail('Contender did not reach the lock barrier; exit '.($worker->getExitCode() ?? 'running').': '.$worker->getErrorOutput().$worker->getOutput());
            }
            usleep(10000);
        }
    }
}
