<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\Repository\TransactionalUnitOfWorkConformanceTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DoctrineTransactionalUnitOfWork::class)]
class DoctrineTransactionalUnitOfWorkTest extends TransactionalUnitOfWorkConformanceTestCase
{
    protected function createTransactionalUnitOfWork(): TransactionalUnitOfWork
    {
        $transactionActive = false;
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->andReturnUsing(
            static function () use (&$transactionActive): bool {
                return $transactionActive;
            },
        );
        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('getConnection')->andReturn($connection);
        $entityManager->shouldReceive('wrapInTransaction')->andReturnUsing(
            static function (callable $operation) use (&$transactionActive): mixed {
                $transactionActive = true;

                try {
                    return $operation();
                } finally {
                    $transactionActive = false;
                }
            },
        );

        return new DoctrineTransactionalUnitOfWork($entityManager);
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
