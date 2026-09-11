<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Repository;

use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Repository\UnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use RuntimeException;

/**
 * Reusable behavioral contract for transactional unit-of-work adapters.
 */
abstract class TransactionalUnitOfWorkConformanceTestCase extends UnitTestCase
{
    public function test_that_transactional_unit_of_work_implements_only_the_narrow_contract(): void
    {
        $unitOfWork = $this->createTransactionalUnitOfWork();

        self::assertInstanceOf(TransactionalUnitOfWork::class, $unitOfWork);
        self::assertNotInstanceOf(UnitOfWork::class, $unitOfWork);
        self::assertFalse(method_exists($unitOfWork, 'commit'));
    }

    public function test_that_transactional_unit_of_work_returns_the_callback_result(): void
    {
        self::assertSame(
            'transaction result',
            $this->createTransactionalUnitOfWork()->commitTransactional(
                static fn(): string => 'transaction result',
            ),
        );
    }

    public function test_that_transactional_unit_of_work_rejects_nested_execution(): void
    {
        $unitOfWork = $this->createTransactionalUnitOfWork();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nested transactional execution is not supported.');

        $unitOfWork->commitTransactional(
            static function () use ($unitOfWork): never {
                $unitOfWork->commitTransactional(static fn(): null => null);
                throw new RuntimeException('The nested transaction should have failed.');
            },
        );
    }

    public function test_that_transactional_unit_of_work_rethrows_the_original_callback_failure(): void
    {
        $failure = new RuntimeException('transaction failure');

        try {
            $this->createTransactionalUnitOfWork()->commitTransactional(
                static function () use ($failure): never {
                    throw $failure;
                },
            );
            self::fail('Expected the transaction failure to be propagated.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    abstract protected function createTransactionalUnitOfWork(): TransactionalUnitOfWork;
}
