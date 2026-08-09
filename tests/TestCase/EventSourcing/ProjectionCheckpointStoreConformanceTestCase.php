<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use Fight\Common\Application\EventSourcing\ProjectionCheckpointStore;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;

abstract class ProjectionCheckpointStoreConformanceTestCase extends UnitTestCase
{
    abstract protected function createProjectionCheckpointStore(): ProjectionCheckpointStore;

    protected function reopenProjectionCheckpointStore(
        ProjectionCheckpointStore $store,
    ): ProjectionCheckpointStore {
        return $store;
    }

    public function test_that_checkpoints_follow_the_durable_lifecycle_contract(): void
    {
        $store = $this->createProjectionCheckpointStore();

        self::assertSame(0, $store->load('orders.order-summary'));
        self::assertSame(0, $store->load('billing.revenue-summary'));

        try {
            $store->save('orders.order-summary', -1);
            self::fail('Expected a checkpoint below the initial position to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Projection checkpoint orders.order-summary cannot move backward from 0 to -1.',
                $exception->getMessage(),
            );
        }

        $store->reset('inventory.stock-summary');
        $store->reset('inventory.stock-summary');
        self::assertSame(0, $store->load('inventory.stock-summary'));

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

        $store = $this->reopenProjectionCheckpointStore($store);

        self::assertSame(5, $store->load('orders.order-summary'));
        self::assertSame(2, $store->load('billing.revenue-summary'));

        $store->reset('orders.order-summary');
        $store->reset('orders.order-summary');
        $store = $this->reopenProjectionCheckpointStore($store);

        self::assertSame(0, $store->load('orders.order-summary'));
        self::assertSame(2, $store->load('billing.revenue-summary'));
        self::assertSame(0, $store->load('inventory.stock-summary'));
    }
}
