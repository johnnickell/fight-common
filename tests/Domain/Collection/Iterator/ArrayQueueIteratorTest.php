<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Iterator;

use Fight\Common\Domain\Collection\Iterator\ArrayQueueIterator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArrayQueueIterator::class)]
class ArrayQueueIteratorTest extends UnitTestCase
{
    public function test_that_valid_returns_true_when_items_remain(): void
    {
        $iterator = new ArrayQueueIterator(['a', 'b'], 0, 4);

        self::assertTrue($iterator->valid());
    }

    public function test_that_valid_returns_false_when_empty(): void
    {
        $iterator = new ArrayQueueIterator([], 0, 4);

        self::assertFalse($iterator->valid());
    }

    public function test_that_key_returns_current_index(): void
    {
        $iterator = new ArrayQueueIterator(['x', 'y'], 0, 4);

        self::assertSame(0, $iterator->key());
    }

    public function test_that_key_returns_null_when_exhausted(): void
    {
        $iterator = new ArrayQueueIterator([], 0, 4);

        self::assertNull($iterator->key());
    }

    public function test_that_current_returns_item_at_front_offset(): void
    {
        // items stored at indices 0..3; front=1 means logical[0] is items[1]
        $items = ['ignored', 'first', 'second', 'third'];
        $iterator = new ArrayQueueIterator($items, 1, 4);

        self::assertSame('first', $iterator->current());
    }

    public function test_that_current_wraps_when_front_plus_offset_exceeds_capacity(): void
    {
        // front=3, cap=4: logical[0]=items[3], logical[1]=items[0]
        $items = ['wrap-target', 'b', 'c', 'first'];
        $iterator = new ArrayQueueIterator($items, 3, 4);

        self::assertSame('first', $iterator->current());

        $iterator->next();

        self::assertSame('wrap-target', $iterator->current());
    }

    public function test_that_current_returns_null_when_exhausted(): void
    {
        $iterator = new ArrayQueueIterator([], 0, 4);

        self::assertNull($iterator->current());
    }

    public function test_that_next_advances_index(): void
    {
        $items = ['a', 'b', 'c'];
        $iterator = new ArrayQueueIterator($items, 0, 3);
        $iterator->next();

        self::assertSame(1, $iterator->key());
        self::assertSame('b', $iterator->current());
    }

    public function test_that_rewind_resets_index_to_zero(): void
    {
        $items = ['a', 'b', 'c'];
        $iterator = new ArrayQueueIterator($items, 0, 3);
        $iterator->next();
        $iterator->next();
        $iterator->rewind();

        self::assertSame(0, $iterator->key());
        self::assertSame('a', $iterator->current());
    }

    public function test_that_full_iteration_visits_all_items_in_logical_order(): void
    {
        // front=2, cap=4: logical order is items[2], items[3], items[0], items[1]
        $items = ['third', 'fourth', 'first', 'second'];
        $iterator = new ArrayQueueIterator($items, 2, 4);
        $collected = [];

        for (; $iterator->valid(); $iterator->next()) {
            $collected[] = $iterator->current();
        }

        self::assertSame(['first', 'second', 'third', 'fourth'], $collected);
    }
}
