<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Fight\Common\Adapter\Repository\DoctrineUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Repository\UnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DoctrineUnitOfWork::class)]
class DoctrineUnitOfWorkTest extends UnitTestCase
{
    public function test_that_deprecated_legacy_commit_flushes_the_entity_manager(): void
    {
        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('flush')->once()->withNoArgs();

        $unitOfWork = new DoctrineUnitOfWork($entityManager);
        $unitOfWork->commit();
    }

    public function test_that_commit_transactional_wraps_operation_in_transaction(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();

        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);
        $entityManager->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn(callable $operation) => $operation());

        $transactionalUnitOfWork = new DoctrineUnitOfWork($entityManager);
        $result = $transactionalUnitOfWork->commitTransactional(fn(): string => 'done');

        self::assertSame('done', $result);
    }

    public function test_that_implements_transactional_and_legacy_unit_of_work_contracts(): void
    {
        $entityManager = $this->mock(EntityManagerInterface::class);
        $unitOfWork = new DoctrineUnitOfWork($entityManager);

        self::assertInstanceOf(TransactionalUnitOfWork::class, $unitOfWork);
        self::assertInstanceOf(UnitOfWork::class, $unitOfWork);
    }

    public function test_that_commit_transactional_rejects_an_active_doctrine_transaction(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnTrue();

        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);
        $entityManager->shouldNotReceive('wrapInTransaction');

        $transactionalUnitOfWork = new DoctrineUnitOfWork($entityManager);

        $this->expectException(\LogicException::class);
        $transactionalUnitOfWork->commitTransactional(static fn(): null => null);
    }

    public function test_that_commit_transactional_propagates_callback_failures_through_doctrine(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();

        $failure = new \RuntimeException('transaction failure');
        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);
        $entityManager->shouldReceive('wrapInTransaction')->once()->andReturnUsing(
            static fn(callable $operation): mixed => $operation()
        );

        $transactionalUnitOfWork = new DoctrineUnitOfWork($entityManager);

        $this->expectExceptionObject($failure);
        $transactionalUnitOfWork->commitTransactional(static function () use ($failure): never {
            throw $failure;
        });
    }

    public function test_that_is_closed_returns_true_when_entity_manager_is_not_open(): void
    {
        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('isOpen')->once()->andReturnFalse();

        $unitOfWork = new DoctrineUnitOfWork($entityManager);
        self::assertTrue($unitOfWork->isClosed());
    }

    public function test_that_is_closed_returns_false_when_entity_manager_is_open(): void
    {
        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('isOpen')->once()->andReturnTrue();

        $unitOfWork = new DoctrineUnitOfWork($entityManager);
        self::assertFalse($unitOfWork->isClosed());
    }
}
