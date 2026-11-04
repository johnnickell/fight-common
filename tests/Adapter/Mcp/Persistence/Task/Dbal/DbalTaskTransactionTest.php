<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskRepository;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTransaction;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\TaskSelection;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(DbalTaskTransaction::class)]
#[CoversClass(DbalTaskRepository::class)]
final class DbalTaskTransactionTest extends UnitTestCase
{
    public function test_that_only_the_exact_serialized_boundary_can_admit_repository_operations(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalTaskSchema())->install($connection);
        $transactionalUnitOfWork = new DbalTaskTransaction($connection);
        $taskRepository = new DbalTaskRepository($transactionalUnitOfWork);
        $task = (new CreateTask(TaskFixture::definition(), TaskState::create('working')))->at(1);
        $otherTaskTransaction = new DbalTaskTransaction($connection);
        $genericTransaction = new DbalTransactionalUnitOfWork($connection);

        foreach ([$otherTaskTransaction, $genericTransaction] as $unownedTransaction) {
            foreach ([
                fn () => $taskRepository->getById('scope', TaskFixture::id()),
                fn () => $taskRepository->save($task),
                fn () => $taskRepository->remove(TaskSelection::fromIds('scope', [TaskFixture::id()])),
                fn () => $taskRepository->read('scope', TaskFixture::id())
            ] as $operation) {
                try {
                    $unownedTransaction->commitTransactional($operation);
                    self::fail('An unrelated transaction cannot impersonate the repository boundary.');
                } catch (LogicException) {
                    self::assertFalse($connection->isTransactionActive());
                    self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
                }
            }
        }

        $result = $transactionalUnitOfWork->commitTransactional(function () use ($taskRepository, $task) {
            $taskRepository->save($task);
            return $taskRepository->getById('scope', TaskFixture::id());
        });
        self::assertEquals($task->toArray(), $result->toArray());
        self::assertEquals($task->toArray(), $taskRepository->read('scope', TaskFixture::id())->toArray());
        self::assertFalse($connection->isTransactionActive());
        self::assertFalse($transactionalUnitOfWork->isClosed());

        // A confirmed callback rollback preserves both the throwable and reusable ownership.
        $cause = new RuntimeException('Consumer failure');
        try {
            $transactionalUnitOfWork->commitTransactional(function () use ($taskRepository, $cause): void {
                $taskRepository->remove(TaskSelection::fromIds('scope', [TaskFixture::id()]));
                throw $cause;
            });
            self::fail('Callback failure must propagate.');
        } catch (RuntimeException $failure) {
            self::assertSame($cause, $failure);
        }
        self::assertNotNull($taskRepository->read('scope', TaskFixture::id()));
        self::assertNull($transactionalUnitOfWork->commitTransactional(
            fn () => $taskRepository->getById('scope', TaskFixture::id(str_repeat('f', 32)))
        ));
    }

    public function test_that_read_isolation_is_rechecked_after_construction_without_admitting_work(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalTaskSchema())->install($connection);
        $fixture = new TaskFixture($connection);
        $fixture->create();
        $before = $connection->fetchOne('SELECT payload FROM fight_tasks');
        $connection->setTransactionIsolation(TransactionIsolationLevel::READ_UNCOMMITTED);
        foreach ([fn () => $fixture->get(), fn () => $fixture->record(TaskFixture::completed())] as $operation) {
            try {
                $operation();
                self::fail('Dirty reads must reject.');
            } catch (LogicException) {
                self::assertSame($before, $connection->fetchOne('SELECT payload FROM fight_tasks'));
                self::assertSame(1, $fixture->admissions);
            }
        }
    }

    public function test_that_pretransaction_dbal_failures_are_not_mislabeled_as_an_owned_storage_failure(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new SQLitePlatform());
        $connection->shouldReceive('isTransactionActive')->andReturn(false);
        $connection->shouldReceive('getTransactionIsolation')->andReturn(TransactionIsolationLevel::SERIALIZABLE);
        $cause = new class ('Connection unavailable') extends RuntimeException implements Exception {};
        $connection->shouldReceive('isAutoCommit')->once()->andThrow($cause);
        $transactionalUnitOfWork = new DbalTaskTransaction($connection);
        try {
            $transactionalUnitOfWork->commitTransactional(static fn () => self::fail('No callback'));
            self::fail('Connection failure must propagate.');
        } catch (Exception $failure) {
            self::assertSame($cause, $failure);
            self::assertFalse($transactionalUnitOfWork->isClosed());
        }
    }

    public function test_that_schema_lock_failure_rolls_back_before_admission_and_keeps_the_boundary_reusable(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $fixture = new TaskFixture($connection);
        try {
            $fixture->create();
            self::fail('Missing schema cannot admit work.');
        } catch (TaskStorageFailed $failure) {
            self::assertFalse($failure->outcomeUncertain());
            self::assertSame(0, $fixture->admissions);
            self::assertFalse($connection->isTransactionActive());
            self::assertFalse($fixture->transactionalUnitOfWork->isClosed());
        }
        (new DbalTaskSchema())->install($connection);
        $fixture->create();
        self::assertSame('working', $fixture->get()->get('state')->get('status'));
    }
}
