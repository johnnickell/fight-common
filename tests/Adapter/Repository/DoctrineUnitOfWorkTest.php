<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Fight\Common\Adapter\Repository\DoctrineUnitOfWork;
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
        $em = $this->mock(EntityManagerInterface::class);
        $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn(callable $fn) => $fn());

        $uow = new DoctrineUnitOfWork($em);
        $result = $uow->commitTransactional(fn(): string => 'done');

        self::assertSame('done', $result);
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
