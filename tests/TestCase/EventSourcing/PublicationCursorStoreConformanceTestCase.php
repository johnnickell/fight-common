<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use Fight\Common\Application\EventSourcing\PublicationCursorStore;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;

/**
 * Class PublicationCursorStoreConformanceTestCase
 *
 * Reusable behavioral contract for publication cursor stores
 */
abstract class PublicationCursorStoreConformanceTestCase extends UnitTestCase
{
    /**
     * Verifies named cursor lifecycle behavior
     */
    public function test_that_named_publication_cursors_follow_the_durable_lifecycle_contract(): void
    {
        $store = $this->createPublicationCursorStore();

        self::assertSame(0, $store->load('orders.primary'));
        self::assertSame(0, $store->load('orders.secondary'));

        try {
            $store->save('orders.primary', -1);
            self::fail('Expected a cursor below the initial position to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Publication cursor orders.primary cannot move backward from 0 to -1.',
                $exception->getMessage(),
            );
        }

        $store->save('orders.primary', 3);
        $store->save('orders.primary', 3);
        $store->save('orders.primary', 5);
        $store->save('orders.secondary', 2);

        self::assertSame(5, $store->load('orders.primary'));
        self::assertSame(2, $store->load('orders.secondary'));

        try {
            $store->save('orders.primary', 4);
            self::fail('Expected a backward cursor save to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Publication cursor orders.primary cannot move backward from 5 to 4.',
                $exception->getMessage(),
            );
        }

        $store = $this->reopenPublicationCursorStore($store);

        self::assertSame(5, $store->load('orders.primary'));
        self::assertSame(2, $store->load('orders.secondary'));
    }

    /**
     * Creates the publication cursor store under test
     */
    abstract protected function createPublicationCursorStore(): PublicationCursorStore;

    /**
     * Returns a reopened publication cursor store when supported
     */
    protected function reopenPublicationCursorStore(
        PublicationCursorStore $store,
    ): PublicationCursorStore {
        return $store;
    }
}
