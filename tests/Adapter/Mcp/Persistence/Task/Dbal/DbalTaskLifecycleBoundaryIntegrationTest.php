<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\Command\RemoveTasks;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Event\TaskDataRemoved;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\TaskSelection;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\EventMessage;
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
final class DbalTaskLifecycleBoundaryIntegrationTest extends UnitTestCase
{
    #[DataProvider('backends')]
    public function test_that_task_update_distinguishes_constraint_failure_lost_ack_and_failed_dispatch(string $backend): void
    {
        $this->withStorage($backend, function (Connection $connection, TaskCheckConnection $faulted, TaskFixture $writer, TaskFixture $reader, DbalTaskTables $tables, string $taskTable) use ($backend): void {
            $writer->create();
            $query = self::query();
            $complete = self::complete();
            $before = $reader->getTaskHandler->handle($query)->toString();
            $connection->executeStatement(match ($backend) {
                'sqlite' => "CREATE TRIGGER task_boundary_fail BEFORE INSERT ON $taskTable BEGIN SELECT RAISE(ABORT, 'private'); END",
                default => "ALTER TABLE $taskTable ADD CONSTRAINT task_boundary_fail CHECK (payload NOT LIKE '%completed%')"
            });
            try {
                $writer->recordTaskStateHandler->handle($complete);
                self::fail('The constraint must reject the terminal update.');
            } catch (TaskStorageFailed $failure) {
                self::assertFalse($failure->outcomeUncertain());
                self::assertSame($before, $reader->getTaskHandler->handle($query)->toString());
            }
            $connection->executeStatement(match ($backend) {
                'sqlite' => 'DROP TRIGGER task_boundary_fail',
                'mysql' => "ALTER TABLE $taskTable DROP CHECK task_boundary_fail",
                'postgresql' => "ALTER TABLE $taskTable DROP CONSTRAINT task_boundary_fail"
            });
            $uncertain = new TaskFixture($faulted, $tables);
            $this->assertLostAck($uncertain, $faulted, static fn () => $uncertain->recordTaskStateHandler->handle($complete));
            self::assertSame('completed', $reader->getTaskHandler->handle($query)->get('state')->get('status'));
            $calls = 0;
            $writer->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), static function (EventMessage $event) use ($reader, &$calls): void {
                ++$calls;
                $data = $event->payload()->toArray();
                self::assertSame($data['version'], $reader->getTaskHandler->handle(self::query($data['id']))->get('version'));
                throw new RuntimeException('Private publication failure');
            });
            $writer->recordTaskStateHandler->handle($complete);
            self::assertSame(0, $calls);
            $other = str_repeat('c', 32);
            try {
                $writer->createTaskHandler->handle(CommandMessage::create(new CreateTask(TaskFixture::definition($other), TaskState::create('working'))));
                self::fail('Postcommit failure must not acknowledge dispatch.');
            } catch (CommittedTaskDispatchFailed $failure) {
                self::assertSame(1, $failure->event()->toArray()['version']);
                self::assertNotNull($reader->getTaskHandler->handle(self::query($other)));
            }
            self::assertSame(1, $calls);
        });
    }

    #[DataProvider('backends')]
    public function test_that_removed_task_bodies_stay_gone_after_lost_ack_and_failed_dispatch(string $backend): void
    {
        $this->withStorage($backend, function (Connection $connection, TaskCheckConnection $faulted, TaskFixture $writer, TaskFixture $reader, DbalTaskTables $tables, string $taskTable): void {
            $writer->create(1000);
            $writer->issue();
            self::assertSame('input_required', $reader->getTaskHandler->handle(self::query())->get('state')->get('status'));
            $remove = self::remove([TaskFixture::ID]);
            $uncertain = new TaskFixture($faulted, $tables);
            $this->assertLostAck($uncertain, $faulted, static fn () => $uncertain->removeTasksHandler->handle($remove));
            self::assertNull($reader->getTaskHandler->handle(self::query()));
            self::assertSame(0, (int) $connection->fetchOne("SELECT COUNT(*) FROM $taskTable"));
            $writer->removeTasksHandler->handle($remove);
            $writer->removeAllowed = false;
            try {
                $writer->removeTasksHandler->handle($remove);
                self::fail('A missing repeat must reauthorize.');
            } catch (TaskRejected) {
                self::assertCount(2, $writer->removalChecks);
            }
            try {
                $writer->recordTaskStateHandler->handle(self::complete());
                self::fail('A late update must not recreate removed data.');
            } catch (TaskRejected) {
                self::assertNull($reader->getTaskHandler->handle(self::query()));
            }
            $writer->removeAllowed = true;
            $writer->now += 500;
            $reader->now = $writer->now;
            $writer->create(2000);
            $recreated = $reader->getTaskHandler->handle(self::query());
            self::assertSame(1, $recreated->get('version'));
            self::assertSame($writer->now, $recreated->get('created_at'));
            $facts = [];
            $writer->eventDispatcher->addHandler(ClassName::underscore(TaskDataRemoved::class), static function (EventMessage $event) use ($reader, &$facts): void {
                self::assertNull($reader->getTaskHandler->handle(self::query()));
                $facts[] = $event->payload()->toArray();
                throw new RuntimeException('Private publication failure');
            });
            try {
                $writer->removeTasksHandler->handle($remove);
                self::fail('The committed removal cannot acknowledge failed dispatch.');
            } catch (CommittedTaskDispatchFailed $failure) {
                self::assertSame(['scope' => 'scope', 'ids' => [TaskFixture::ID]], $failure->event()->toArray());
            }
            $writer->removeTasksHandler->handle($remove);
            self::assertCount(1, $facts);
            self::assertSame(0, (int) $connection->fetchOne("SELECT COUNT(*) FROM $taskTable"));
            self::assertNull($reader->getTaskHandler->handle(self::query()));
        });
    }

    #[DataProvider('backends')]
    public function test_that_task_removal_checks_the_whole_selection_and_leaves_foreign_transaction_work_owned(string $backend): void
    {
        $this->withStorage($backend, static function (Connection $connection, TaskCheckConnection $faulted, TaskFixture $writer, TaskFixture $reader, DbalTaskTables $tables, string $taskTable, Connection $observer): void {
            $other = str_repeat('a', 32);
            $writer->create(10);
            $writer->recordTaskStateHandler->handle(self::complete());
            $writer->create(id: $other);
            $read = static fn () => $observer->fetchAllAssociative("SELECT * FROM $taskTable ORDER BY task_id");
            $before = $read();
            $both = self::remove([TaskFixture::ID, $other]);
            $writer->deniedRemovalIds = [$other];
            foreach ([$both, self::remove([TaskFixture::ID], 'SUPER_ADMIN')] as $command) {
                try {
                    $writer->removeTasksHandler->handle($command);
                    self::fail('Denied identities must reject the entire selection.');
                } catch (TaskRejected $failure) {
                    self::assertSame('Task operation rejected.', $failure->getMessage());
                    self::assertSame($before, $read());
                }
            }
            $writer->deniedRemovalIds = [];
            $connection->executeStatement('CREATE TABLE task_boundary_caller (id INTEGER)');
            try {
                $connection->beginTransaction();
                $connection->insert('task_boundary_caller', ['id' => 7]);
                $checks = $writer->removalChecks;
                try {
                    $writer->removeTasksHandler->handle($both);
                    self::fail('Removal must not enlist caller work.');
                } catch (LogicException) {
                    self::assertSame($checks, $writer->removalChecks);
                    self::assertTrue($connection->isTransactionActive());
                    self::assertSame(7, (int) $connection->fetchOne('SELECT id FROM task_boundary_caller'));
                    self::assertSame(0, (int) $observer->fetchOne('SELECT COUNT(*) FROM task_boundary_caller'));
                    self::assertSame($before, $read());
                }
                $connection->commit();
                self::assertSame(7, (int) $observer->fetchOne('SELECT id FROM task_boundary_caller'));
            } finally {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                $connection->executeStatement('DROP TABLE task_boundary_caller');
            }
            $reader->now += 10;
            $writer->now = $reader->now;
            self::assertNull($reader->getTaskHandler->handle(self::query()));
            self::assertNotNull($reader->getTaskHandler->handle(self::query($other)));
            self::assertSame($before, $read(), 'Expiry reads cannot clean bodies or comparison facts.');
            $writer->createAllowed = false;
            $writer->updateAllowed = false;
            $writer->readAllowed = false;
            $writer->removeTasksHandler->handle($both);
            self::assertSame([], $read());
            self::assertNull($reader->getTaskHandler->handle(self::query()));
            self::assertNull($reader->getTaskHandler->handle(self::query($other)));
        });
    }

    public static function backends(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend => [$backend];
        }
    }

    private static function query(string $id = TaskFixture::ID): QueryMessage
    {
        return QueryMessage::create(new GetTask('scope', TaskFixture::id($id)));
    }

    private static function complete(): CommandMessage
    {
        return CommandMessage::create(new RecordTaskState('scope', TaskFixture::id(), 'producer', 1, TaskFixture::completed()));
    }

    private static function remove(array $ids, string $scope = 'scope'): CommandMessage
    {
        return CommandMessage::create(new RemoveTasks(TaskSelection::fromIds($scope, array_map(TaskFixture::id(...), $ids))));
    }

    private function assertLostAck(TaskFixture $fixture, TaskCheckConnection $connection, callable $operation): void
    {
        $noFact = static fn () => self::fail('Uncertain acknowledgment must not publish a fact.');
        $fixture->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), $noFact);
        $fixture->eventDispatcher->addHandler(ClassName::underscore(TaskDataRemoved::class), $noFact);
        $connection->afterCommit = static fn () => throw new RuntimeException('Lost actual commit acknowledgment');
        try {
            $operation();
            self::fail('A lost acknowledgment cannot report success.');
        } catch (TaskStorageFailed $failure) {
            self::assertTrue($failure->outcomeUncertain());
            self::assertTrue($fixture->transactionalUnitOfWork->isClosed());
            self::assertFalse($connection->isTransactionActive());
        }
    }

    private function withStorage(string $backend, callable $check): void
    {
        $path = $backend === 'sqlite' ? tempnam(sys_get_temp_dir(), 'task-lifecycle-boundary-') : null;
        if ($path === null) {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual disposable database required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        } else {
            $params = ['driver' => 'pdo_sqlite', 'path' => $path];
        }
        $connection = DriverManager::getConnection($params);
        $observer = DriverManager::getConnection($params);
        $faulted = new TaskCheckConnection($params, $connection->getDriver());
        $tables = $backend === 'mysql' ? new DbalTaskTables('LifecycleTaskState', 'Order') : new DbalTaskTables();
        $platform = $connection->getDatabasePlatform();
        $taskTable = $tables->taskTable()->quoted($platform);
        $stateTable = $tables->stateTable()->quoted($platform);
        try {
            $connection->executeStatement("DROP TABLE IF EXISTS $taskTable");
            $connection->executeStatement("DROP TABLE IF EXISTS $stateTable");
            (new DbalTaskSchema($tables))->install($connection);
            $check($connection, $faulted, new TaskFixture($connection, $tables), new TaskFixture($observer, $tables), $tables, $taskTable, $observer);
        } finally {
            $faulted->close();
            $observer->close();
            try {
                $connection->executeStatement("DROP TABLE IF EXISTS $taskTable");
                $connection->executeStatement("DROP TABLE IF EXISTS $stateTable");
            } finally {
                $connection->close();
                if ($path !== null) {
                    unlink($path);
                }
            }
        }
    }
}
