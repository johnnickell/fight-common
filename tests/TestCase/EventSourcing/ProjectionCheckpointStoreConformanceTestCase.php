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
        ProjectionCheckpointStore $projectionCheckpointStore,
    ): ProjectionCheckpointStore {
        return $projectionCheckpointStore;
    }

    public function test_that_checkpoints_follow_the_durable_lifecycle_contract(): void
    {
        $projectionCheckpointStore = $this->createProjectionCheckpointStore();

        self::assertSame(0, $projectionCheckpointStore->load('orders.order-summary'));
        self::assertSame(0, $projectionCheckpointStore->load('billing.revenue-summary'));

        try {
            $projectionCheckpointStore->save('orders.order-summary', -1);
            self::fail('Expected a checkpoint below the initial position to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Projection checkpoint orders.order-summary cannot move backward from 0 to -1.',
                $exception->getMessage(),
            );
        }

        $projectionCheckpointStore->reset('inventory.stock-summary');
        $projectionCheckpointStore->reset('inventory.stock-summary');
        self::assertSame(0, $projectionCheckpointStore->load('inventory.stock-summary'));

        $projectionCheckpointStore->save('orders.order-summary', 3);
        $projectionCheckpointStore->save('orders.order-summary', 3);
        $projectionCheckpointStore->save('orders.order-summary', 5);
        $projectionCheckpointStore->save('billing.revenue-summary', 2);

        self::assertSame(5, $projectionCheckpointStore->load('orders.order-summary'));
        self::assertSame(2, $projectionCheckpointStore->load('billing.revenue-summary'));

        try {
            $projectionCheckpointStore->save('orders.order-summary', 4);
            self::fail('Expected a backward checkpoint save to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Projection checkpoint orders.order-summary cannot move backward from 5 to 4.',
                $exception->getMessage(),
            );
        }

        $projectionCheckpointStore = $this->reopenProjectionCheckpointStore($projectionCheckpointStore);

        self::assertSame(5, $projectionCheckpointStore->load('orders.order-summary'));
        self::assertSame(2, $projectionCheckpointStore->load('billing.revenue-summary'));

        $projectionCheckpointStore->reset('orders.order-summary');
        $projectionCheckpointStore->reset('orders.order-summary');
        $projectionCheckpointStore = $this->reopenProjectionCheckpointStore($projectionCheckpointStore);

        self::assertSame(0, $projectionCheckpointStore->load('orders.order-summary'));
        self::assertSame(2, $projectionCheckpointStore->load('billing.revenue-summary'));
        self::assertSame(0, $projectionCheckpointStore->load('inventory.stock-summary'));
    }
}
