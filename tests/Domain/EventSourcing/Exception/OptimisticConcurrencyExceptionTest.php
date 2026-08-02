<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\EventSourcing\Exception;

use Fight\Common\Domain\EventSourcing\Exception\OptimisticConcurrencyException;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OptimisticConcurrencyException::class)]
class OptimisticConcurrencyExceptionTest extends UnitTestCase
{
    public function test_that_it_identifies_the_conflicting_stream_and_versions(): void
    {
        $streamId = new StreamId('orders', 'order-42');

        $exception = new OptimisticConcurrencyException($streamId, 3, 5);

        self::assertInstanceOf(DomainException::class, $exception);
        self::assertSame($streamId, $exception->streamId());
        self::assertSame(3, $exception->expectedVersion());
        self::assertSame(5, $exception->actualVersion());
        self::assertSame(
            'Optimistic concurrency conflict for stream "orders"/"order-42": expected version 3, actual version 5',
            $exception->getMessage(),
        );
    }
}
