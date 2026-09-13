<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Persistence\CodeIgniter;

use CodeIgniter\Database\BaseConnection;
use Fight\Common\Adapter\Persistence\CodeIgniter\CodeIgniterTransactionalUnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(CodeIgniterTransactionalUnitOfWork::class)]
final class CodeIgniterTransactionalUnitOfWorkEdgeCaseTest extends UnitTestCase
{
    public function test_that_a_successful_callback_is_committed_and_its_result_is_returned(): void
    {
        $connection = $this->connection();
        $connection->shouldReceive('getConnection')->twice()->andReturnFalse();
        $connection->shouldReceive('transBegin')->once()->andReturnTrue();
        $connection->shouldReceive('transStatus')->once()->andReturnTrue();
        $connection->shouldReceive('transCommit')->once()->andReturnTrue();

        self::assertSame(
            'committed result',
            (new CodeIgniterTransactionalUnitOfWork($connection))->commitTransactional(
                static fn (): string => 'committed result',
            ),
        );
    }

    public function test_that_a_callback_failure_is_rethrown_after_transaction_cleanup(): void
    {
        $failure = new RuntimeException('callback failed');
        $connection = $this->connection();
        $connection->shouldReceive('getConnection')->twice()->andReturnFalse();
        $connection->shouldReceive('transBegin')->once()->andReturnTrue();
        $connection->shouldReceive('transRollback')->once();
        $connection->shouldReceive('resetTransStatus')->once();
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);

        $this->expectExceptionObject($failure);

        $unitOfWork->commitTransactional(static function () use ($failure): never {
            throw $failure;
        });
    }

    public function test_that_a_failed_native_transaction_status_is_rolled_back(): void
    {
        $connection = $this->connection();
        $connection->shouldReceive('getConnection')->twice()->andReturnFalse();
        $connection->shouldReceive('transBegin')->once()->andReturnTrue();
        $connection->shouldReceive('transStatus')->once()->andReturnFalse();
        $connection->shouldReceive('transRollback')->once();
        $connection->shouldReceive('resetTransStatus')->once();
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CodeIgniter transaction failed.');

        $unitOfWork->commitTransactional(static fn (): null => null);
    }

    public function test_that_nested_execution_is_rejected_without_starting_another_transaction(): void
    {
        $connection = $this->connection();
        $connection->shouldReceive('getConnection')->once()->andReturnFalse();
        $connection->shouldReceive('getConnection')->once()->andReturnFalse();
        $connection->shouldReceive('getConnection')->once()->andReturn(new \stdClass());
        $connection->shouldReceive('transBegin')->once()->andReturnTrue();
        $connection->shouldReceive('transStatus')->once()->andReturnTrue();
        $connection->shouldReceive('transCommit')->once()->andReturnTrue();
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);

        self::assertSame('outer result', $unitOfWork->commitTransactional(
            static function () use ($unitOfWork): string {
                try {
                    $unitOfWork->commitTransactional(static fn (): null => null);
                    self::fail('Nested transactional execution must be rejected.');
                } catch (\LogicException $exception) {
                    self::assertSame('Nested transactional execution is not supported.', $exception->getMessage());
                }

                return 'outer result';
            },
        ));
    }

    public function test_that_an_explicitly_closed_connection_is_rejected(): void
    {
        $connection = $this->mock(BaseConnection::class);
        $connection->shouldReceive('getConnection')->once()->andReturn(new \stdClass());
        $connection->shouldReceive('getConnection')->once()->andReturnFalse();
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Transactional execution is not supported on a closed connection.');

        $unitOfWork->commitTransactional(static fn (): null => null);
    }

    public function test_that_a_native_begin_failure_is_visible(): void
    {
        $connection = $this->connection();
        $connection->shouldReceive('getConnection')->twice()->andReturnFalse();
        $connection->shouldReceive('transBegin')->once()->andReturnFalse();
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not begin CodeIgniter transaction.');

        $unitOfWork->commitTransactional(static fn (): null => null);
    }

    public function test_that_a_native_commit_failure_is_visible_even_when_rollback_cleanup_fails(): void
    {
        $connection = $this->connection();
        $connection->shouldReceive('getConnection')->twice()->andReturnFalse();
        $connection->shouldReceive('transBegin')->once()->andReturnTrue();
        $connection->shouldReceive('transStatus')->once()->andReturnTrue();
        $connection->shouldReceive('transCommit')->once()->andReturnFalse();
        $connection->shouldReceive('transRollback')->once()->andThrow(new RuntimeException('rollback failed'));
        $connection->shouldReceive('resetTransStatus')->once()->andThrow(new RuntimeException('reset failed'));
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not commit CodeIgniter transaction.');

        $unitOfWork->commitTransactional(static fn (): null => null);
    }

    /**
     * Creates a mock native connection with no active native transaction.
     *
     * @return BaseConnection<mixed, mixed>
     */
    private function connection(): BaseConnection
    {
        $connection = $this->mock(BaseConnection::class);
        $transactionDepth = new \ReflectionProperty(BaseConnection::class, 'transDepth');
        $transactionDepth->setValue($connection, 0);

        return $connection;
    }
}
