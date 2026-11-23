<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskCancellation;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Mcp\Task\TaskCheckConnection;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversNothing]
final class DbalTaskCancellationFaultIntegrationTest extends UnitTestCase
{
    #[DataProvider('selections')]
    public function test_that_the_cancellation_handler_distinguishes_real_storage_faults_from_committed_intent(
        string $backend,
        DbalTaskTables $tables
    ): void {
        $path = $backend === 'sqlite' ? tempnam(sys_get_temp_dir(), 'task-cancel-fault-') : null;
        $connection = $this->connect($backend, $path);
        $params = $connection->getParams();
        $reader = DriverManager::getConnection($params);
        $platform = $connection->getDatabasePlatform();
        $taskTable = $tables->taskTable()->quoted($platform);
        $stateTable = $tables->stateTable()->quoted($platform);
        $checked = new TaskCheckConnection($params, $connection->getDriver());
        $command = CommandMessage::create(new RequestTaskCancellation('scope', TaskFixture::id(), 'caller'));
        $query = QueryMessage::create(new GetTaskCancellation('scope', TaskFixture::id(), 'caller'));
        $payload = static fn () => $reader->fetchOne("SELECT payload FROM $taskTable");
        $outerTable = 'task_cancel_fault_caller';
        $ownsOuterTable = false;

        try {
            // These cases run on the wrapper's disposable databases. Replace only the selected Task tables.
            $connection->executeStatement("DROP TABLE IF EXISTS $taskTable");
            $connection->executeStatement("DROP TABLE IF EXISTS $stateTable");
            (new DbalTaskSchema($tables))->install($connection);
            $writer = new TaskFixture($connection, $tables);
            $observer = new TaskFixture($reader, $tables);
            $faulted = new TaskFixture($checked, $tables);
            $writer->create();
            $writer->issue(confirmation: true);
            $original = $payload();

            $checked->beforeCommit = static fn () => throw new RuntimeException('Before actual commit');
            $this->assertStorageFailure($faulted, $command, true);
            self::assertSame($original, $payload());
            self::assertFalse($checked->isTransactionActive());
            self::assertTrue($faulted->transactionalUnitOfWork->isClosed());

            $connection->executeStatement(match ($backend) {
                'sqlite' => "CREATE TRIGGER cancel_fault BEFORE INSERT ON $taskTable BEGIN SELECT RAISE(ABORT, 'private'); END",
                default  => "ALTER TABLE $taskTable ADD CONSTRAINT cancel_fault CHECK (payload LIKE '%cancellation_requested_at\":null%')"
            });
            $this->assertStorageFailure($writer, $command, false);
            self::assertSame($original, $payload());
            self::assertFalse($connection->isTransactionActive());
            $connection->executeStatement(match ($backend) {
                'sqlite'     => 'DROP TRIGGER cancel_fault',
                'mysql'      => "ALTER TABLE $taskTable DROP CHECK cancel_fault",
                'postgresql' => "ALTER TABLE $taskTable DROP CONSTRAINT cancel_fault"
            });

            $checked->beforeCommit = null;
            $checked->afterCommit = static fn () => throw new RuntimeException('Lost actual commit response');
            $faulted = new TaskFixture($checked, $tables);
            $this->assertStorageFailure($faulted, $command, true);
            self::assertTrue($faulted->transactionalUnitOfWork->isClosed());
            self::assertSame(3, $observer->getTaskCancellationHandler->handle($query)->get('cancellation')->get('version'));
            $committed = $payload();
            $writer->requestTaskCancellationHandler->handle($command);
            self::assertSame($committed, $payload());

            $otherId = str_repeat('b', 32);
            $writer->create(id: $otherId);
            $otherCommand = CommandMessage::create(new RequestTaskCancellation('scope', TaskFixture::id($otherId), 'caller'));
            $otherQuery = QueryMessage::create(new GetTaskCancellation('scope', TaskFixture::id($otherId), 'caller'));
            $calls = 0;
            $writer->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$calls): void {
                ++$calls;
                throw new RuntimeException('Private postcommit callback');
            });
            try {
                $writer->requestTaskCancellationHandler->handle($otherCommand);
                self::fail('Postcommit dispatch failure must not report rollback.');
            } catch (CommittedTaskDispatchFailed) {
                self::assertSame(2, $observer->getTaskCancellationHandler->handle($otherQuery)->get('cancellation')->get('version'));
            }
            $writer->requestTaskCancellationHandler->handle($otherCommand);
            self::assertSame(1, $calls);

            $connection->executeStatement("CREATE TABLE $outerTable (id INTEGER)");
            $ownsOuterTable = true;
            $connection->beginTransaction();
            $connection->insert($outerTable, ['id' => 7]);
            try {
                $writer->requestTaskCancellationHandler->handle($command);
                self::fail('An active caller transaction cannot acknowledge even an exact retry.');
            } catch (LogicException) {
                self::assertTrue($connection->isTransactionActive());
                self::assertSame(7, (int) $connection->fetchOne("SELECT id FROM $outerTable"));
                self::assertSame(0, (int) $reader->fetchOne("SELECT COUNT(*) FROM $outerTable"));
            }
            $connection->commit();
            self::assertSame(7, (int) $reader->fetchOne("SELECT id FROM $outerTable"));
        } finally {
            $checked->close();
            $reader->close();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            if ($ownsOuterTable) {
                $connection->executeStatement("DROP TABLE $outerTable");
            }
            $connection->executeStatement("DROP TABLE IF EXISTS $taskTable");
            $connection->executeStatement("DROP TABLE IF EXISTS $stateTable");
            $connection->close();
            if ($path !== null) {
                unlink($path);
            }
        }
    }

    public static function selections(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend => [$backend, new DbalTaskTables()];
            yield $backend.'-configured' => [$backend, new DbalTaskTables('TaskState', 'TaskRows')];
        }
    }

    private function assertStorageFailure(TaskFixture $fixture, CommandMessage $message, bool $uncertain): void
    {
        try {
            $fixture->requestTaskCancellationHandler->handle($message);
            self::fail('A storage failure cannot acknowledge cancellation.');
        } catch (TaskStorageFailed $failure) {
            self::assertSame($uncertain, $failure->outcomeUncertain());
        }
    }

    private function connect(string $backend, ?string $path): Connection
    {
        if ($path !== null) {
            return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        }

        $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
        self::assertIsString($dsn, 'Actual disposable server required.');

        return DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn));
    }
}
