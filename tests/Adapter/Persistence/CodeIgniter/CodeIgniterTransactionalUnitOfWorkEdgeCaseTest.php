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
    public function test_that_a_native_begin_failure_is_visible(): void
    {
        $connection = $this->mock(BaseConnection::class);
        $connection->shouldReceive('getConnection')->twice()->andReturnFalse();
        $connection->shouldReceive('transBegin')->once()->andReturnFalse();
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not begin CodeIgniter transaction.');

        $unitOfWork->commitTransactional(static fn (): null => null);
    }

    public function test_that_a_native_commit_failure_is_visible_even_when_rollback_cleanup_fails(): void
    {
        $connection = $this->mock(BaseConnection::class);
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
}
