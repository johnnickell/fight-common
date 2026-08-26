<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Repository;

use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
class TransactionalUnitOfWorkTest extends UnitTestCase
{
    public function test_that_consumer_can_implement_transactional_contract_and_return_callback_result(): void
    {
        $unitOfWork = new class implements TransactionalUnitOfWork {
            public function commitTransactional(callable $operation): mixed
            {
                return $operation();
            }

            public function isClosed(): bool
            {
                return false;
            }
        };

        self::assertSame('committed', $unitOfWork->commitTransactional(fn(): string => 'committed'));
        self::assertFalse($unitOfWork->isClosed());
    }
}
