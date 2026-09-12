<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Persistence\Yii;

use Fight\Common\Adapter\Persistence\Yii\YiiTransactionalUnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Transaction\TransactionInterface;

#[CoversClass(YiiTransactionalUnitOfWork::class)]
final class YiiTransactionalUnitOfWorkUnitTest extends UnitTestCase
{
    public function test_that_it_executes_the_operation_through_the_native_transaction(): void
    {
        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('isActive')->twice()->andReturnFalse();
        $connection->shouldReceive('getTransaction')->once()->andReturnNull();
        $connection->shouldReceive('transaction')->once()->andReturnUsing(
            static fn (callable $operation): mixed => $operation(),
        );

        self::assertSame(
            'transaction result',
            (new YiiTransactionalUnitOfWork($connection))->commitTransactional(
                static fn (): string => 'transaction result',
            ),
        );
    }

    public function test_that_a_connection_closed_after_establishment_is_rejected(): void
    {
        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('isActive')->once()->andReturnTrue();
        $connection->shouldReceive('isActive')->once()->andReturnFalse();
        $unitOfWork = new YiiTransactionalUnitOfWork($connection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Transactional execution is not supported on a closed connection.');

        $unitOfWork->commitTransactional(static fn (): null => null);
    }

    public function test_that_a_native_transaction_is_not_nested(): void
    {
        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('isActive')->twice()->andReturnFalse();
        $connection->shouldReceive('getTransaction')->once()->andReturn($this->mock(TransactionInterface::class));
        $unitOfWork = new YiiTransactionalUnitOfWork($connection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Nested transactional execution is not supported.');

        $unitOfWork->commitTransactional(static fn (): null => null);
    }

    public function test_that_a_never_opened_connection_is_not_considered_closed(): void
    {
        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('isActive')->once()->andReturnFalse();
        $connection->shouldReceive('isActive')->once()->andReturnFalse();

        self::assertFalse((new YiiTransactionalUnitOfWork($connection))->isClosed());
    }

    public function test_that_a_connection_that_closes_after_being_observed_active_is_terminal(): void
    {
        $connection = $this->mock(ConnectionInterface::class);
        $connection->shouldReceive('isActive')->once()->andReturnFalse();
        $connection->shouldReceive('isActive')->once()->andReturnTrue();
        $connection->shouldReceive('isActive')->once()->andReturnFalse();
        $unitOfWork = new YiiTransactionalUnitOfWork($connection);

        self::assertFalse($unitOfWork->isClosed());
        self::assertTrue($unitOfWork->isClosed());
    }
}
