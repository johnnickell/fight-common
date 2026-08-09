<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\InMemory;

use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryProjectionCheckpointStore;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryProjectionCheckpointStore::class)]
final class InMemoryProjectionCheckpointStoreTest extends UnitTestCase
{
    public function test_that_checkpoints_are_independent_monotonic_and_reset_by_name(): void
    {
        $store = new InMemoryProjectionCheckpointStore();

        self::assertSame(0, $store->load('orders.order-summary'));
        self::assertSame(0, $store->load('billing.revenue-summary'));

        $store->save('orders.order-summary', 3);
        $store->save('orders.order-summary', 3);
        $store->save('orders.order-summary', 5);
        $store->save('billing.revenue-summary', 2);

        self::assertSame(5, $store->load('orders.order-summary'));
        self::assertSame(2, $store->load('billing.revenue-summary'));

        try {
            $store->save('orders.order-summary', 4);
            self::fail('Expected a backward checkpoint save to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Projection checkpoint orders.order-summary cannot move backward from 5 to 4.',
                $exception->getMessage(),
            );
        }

        self::assertSame(5, $store->load('orders.order-summary'));

        $store->reset('orders.order-summary');
        $store->reset('orders.order-summary');

        self::assertSame(0, $store->load('orders.order-summary'));
        self::assertSame(2, $store->load('billing.revenue-summary'));
    }
}
