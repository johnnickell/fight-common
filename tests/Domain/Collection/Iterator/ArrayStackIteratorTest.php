<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Iterator;

use Fight\Common\Domain\Collection\Iterator\ArrayStackIterator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArrayStackIterator::class)]
class ArrayStackIteratorTest extends UnitTestCase
{
    public function test_that_valid_returns_true_when_items_remain(): void
    {
        $iterator = new ArrayStackIterator(['a', 'b', 'c']);

        self::assertTrue($iterator->valid());
    }

    public function test_that_valid_returns_false_when_empty(): void
    {
        $iterator = new ArrayStackIterator([]);

        self::assertFalse($iterator->valid());
    }

    public function test_that_current_returns_last_pushed_item_first(): void
    {
        $iterator = new ArrayStackIterator(['first', 'second', 'last']);

        self::assertSame('last', $iterator->current());
    }

    public function test_that_key_returns_last_index_first(): void
    {
        $iterator = new ArrayStackIterator(['a', 'b', 'c']);

        self::assertSame(2, $iterator->key());
    }

    public function test_that_key_returns_null_when_exhausted(): void
    {
        $iterator = new ArrayStackIterator([]);

        self::assertNull($iterator->key());
    }

    public function test_that_current_returns_null_when_exhausted(): void
    {
        $iterator = new ArrayStackIterator([]);

        self::assertNull($iterator->current());
    }

    public function test_that_next_moves_toward_front(): void
    {
        $iterator = new ArrayStackIterator(['first', 'second', 'third']);
        $iterator->next();

        self::assertSame(1, $iterator->key());
        self::assertSame('second', $iterator->current());
    }

    public function test_that_rewind_resets_to_last_item(): void
    {
        $iterator = new ArrayStackIterator(['a', 'b', 'c']);
        $iterator->next();
        $iterator->next();
        $iterator->rewind();

        self::assertSame(2, $iterator->key());
        self::assertSame('c', $iterator->current());
    }

    public function test_that_full_iteration_visits_items_in_lifo_order(): void
    {
        $iterator = new ArrayStackIterator([10, 20, 30]);
        $collected = [];

        for (; $iterator->valid(); $iterator->next()) {
            $collected[] = $iterator->current();
        }

        self::assertSame([30, 20, 10], $collected);
    }

    public function test_that_valid_returns_false_after_full_traversal(): void
    {
        $iterator = new ArrayStackIterator(['x']);
        $iterator->next();

        self::assertFalse($iterator->valid());
    }
}
