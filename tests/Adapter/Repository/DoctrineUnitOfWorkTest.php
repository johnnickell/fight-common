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
    public function test_that_commit_flushes_the_entity_manager(): void
    {
        $em = $this->mock(EntityManagerInterface::class);
        $em->shouldReceive('flush')->once()->withNoArgs();

        $uow = new DoctrineUnitOfWork($em);
        $uow->commit();
    }

    public function test_that_commit_transactional_wraps_operation_in_transaction(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();

        $em = $this->mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->once()->andReturn($connection);
        $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn(callable $fn) => $fn());

        $uow = new DoctrineUnitOfWork($em);
        $result = $uow->commitTransactional(fn(): string => 'done');

        self::assertSame('done', $result);
    }

    public function test_that_implements_transactional_and_legacy_unit_of_work_contracts(): void
    {
        $em = $this->mock(EntityManagerInterface::class);
        $uow = new DoctrineUnitOfWork($em);

        self::assertInstanceOf(TransactionalUnitOfWork::class, $uow);
        self::assertInstanceOf(UnitOfWork::class, $uow);
    }

    public function test_that_commit_transactional_rejects_an_active_doctrine_transaction(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnTrue();

        $em = $this->mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->once()->andReturn($connection);
        $em->shouldNotReceive('wrapInTransaction');

        $uow = new DoctrineUnitOfWork($em);

        $this->expectException(\LogicException::class);
        $uow->commitTransactional(static fn(): null => null);
    }

    public function test_that_commit_transactional_propagates_callback_failures_through_doctrine(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();

        $failure = new \RuntimeException('transaction failure');
        $em = $this->mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->once()->andReturn($connection);
        $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(
            static fn(callable $operation): mixed => $operation()
        );

        $uow = new DoctrineUnitOfWork($em);

        $this->expectExceptionObject($failure);
        $uow->commitTransactional(static function () use ($failure): never {
            throw $failure;
        });
    }

    public function test_that_is_closed_returns_true_when_entity_manager_is_not_open(): void
    {
        $em = $this->mock(EntityManagerInterface::class);
        $em->shouldReceive('isOpen')->once()->andReturnFalse();

        $uow = new DoctrineUnitOfWork($em);
        self::assertTrue($uow->isClosed());
    }

    public function test_that_is_closed_returns_false_when_entity_manager_is_open(): void
    {
        $em = $this->mock(EntityManagerInterface::class);
        $em->shouldReceive('isOpen')->once()->andReturnTrue();

        $uow = new DoctrineUnitOfWork($em);
        self::assertFalse($uow->isClosed());
    }
}
