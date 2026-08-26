<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Repository\UnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(DoctrineTransactionalUnitOfWork::class)]
class DoctrineTransactionalUnitOfWorkTest extends UnitTestCase
{
    public function test_that_it_implements_only_the_transactional_unit_of_work_contract(): void
    {
        $entityManager = $this->mock(EntityManagerInterface::class);
        $unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);

        self::assertInstanceOf(TransactionalUnitOfWork::class, $unitOfWork);
        self::assertNotInstanceOf(UnitOfWork::class, $unitOfWork);
        self::assertFalse(method_exists($unitOfWork, 'commit'));
    }

    public function test_that_commit_transactional_wraps_operation_in_transaction_and_returns_its_result(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();

        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);
        $entityManager
            ->shouldReceive('wrapInTransaction')
            ->once()
            ->andReturnUsing(static fn(callable $operation): mixed => $operation());

        $unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);

        self::assertSame('transaction result', $unitOfWork->commitTransactional(
            static fn(): string => 'transaction result'
        ));
    }

    public function test_that_commit_transactional_rejects_an_active_doctrine_transaction(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnTrue();

        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);
        $entityManager->shouldNotReceive('wrapInTransaction');

        $unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nested transactional execution is not supported.');
        $unitOfWork->commitTransactional(static fn(): null => null);
    }

    public function test_that_commit_transactional_propagates_the_original_callback_failure(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();

        $failure = new RuntimeException('transaction failure');
        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);
        $entityManager->shouldReceive('wrapInTransaction')->once()->andReturnUsing(
            static fn(callable $operation): mixed => $operation()
        );

        $unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);

        try {
            $unitOfWork->commitTransactional(static function () use ($failure): never {
                throw $failure;
            });
            self::fail('Expected the transaction failure to be propagated.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    public function test_that_is_closed_returns_true_when_the_entity_manager_is_not_open(): void
    {
        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('isOpen')->once()->andReturnFalse();

        $unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);

        self::assertTrue($unitOfWork->isClosed());
    }

    public function test_that_is_closed_returns_false_when_the_entity_manager_is_open(): void
    {
        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('isOpen')->once()->andReturnTrue();

        $unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);

        self::assertFalse($unitOfWork->isClosed());
    }
}
