<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection;

use AssertionError;
use Fight\Common\Domain\Collection\ArrayQueue;
use Fight\Common\Domain\Exception\UnderflowException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArrayQueue::class)]
class ArrayQueueTest extends UnitTestCase
{
    public function test_that_of_creates_a_typed_collection_and_item_type_returns_the_correct_type(): void
    {
        $queue = ArrayQueue::of('string');

        self::assertSame('string', $queue->itemType());
    }

    public function test_that_of_with_no_argument_creates_a_dynamic_collection(): void
    {
        $queue = ArrayQueue::of();

        self::assertNull($queue->itemType());
    }

    public function test_that_enqueue_adds_an_item_and_count_reflects_the_change(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame(2, $queue->count());
    }

    public function test_that_enqueue_throws_for_wrong_type(): void
    {
        $queue = ArrayQueue::of('int');

        $this->expectException(AssertionError::class);
        $queue->enqueue('not-an-int');
    }

    public function test_that_dequeue_removes_and_returns_items_in_fifo_order(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame(1, $queue->dequeue());
        self::assertSame(2, $queue->dequeue());
        self::assertSame(3, $queue->dequeue());
        self::assertSame(0, $queue->count());
    }

    public function test_that_dequeue_throws_underflow_exception_on_an_empty_queue(): void
    {
        $this->expectException(UnderflowException::class);
        ArrayQueue::of('int')->dequeue();
    }

    public function test_that_front_returns_the_next_item_without_removing_it(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame(1, $queue->front());
        self::assertSame(2, $queue->count());
    }

    public function test_that_front_throws_underflow_exception_on_an_empty_queue(): void
    {
        $this->expectException(UnderflowException::class);
        ArrayQueue::of('int')->front();
    }

    public function test_that_circular_buffer_reindexing_maintains_fifo_order(): void
    {
        $queue = ArrayQueue::of('int');

        // Fill the initial capacity of 10 (end pointer wraps to 0)
        for ($i = 1; $i <= 10; $i++) {
            $queue->enqueue($i);
        }

        // Advance the front pointer to 5 by dequeuing items 1-5
        for ($i = 1; $i <= 5; $i++) {
            self::assertSame($i, $queue->dequeue());
        }

        // Enqueue 5 more items — they land at physical indices 0-4 (wrapping)
        for ($i = 11; $i <= 15; $i++) {
            $queue->enqueue($i);
        }

        // One more enqueue fills the buffer and triggers a grow reindex
        $queue->enqueue(16);

        // All remaining items must come out in FIFO order across the wrap
        self::assertSame(range(6, 16), $queue->toArray());
        self::assertSame(11, $queue->count());
    }

    public function test_that_dequeue_shrinks_the_buffer_when_quarter_full(): void
    {
        $queue = ArrayQueue::of('int');

        // Fill to 10, trigger growth to cap=20
        for ($i = 1; $i <= 11; $i++) {
            $queue->enqueue($i);
        }

        // Dequeue until count reaches cap/4 = 20/4 = 5, triggering shrink
        for ($i = 1; $i <= 6; $i++) {
            $queue->dequeue();
        }

        // Queue should still return remaining items in FIFO order
        self::assertSame(range(7, 11), $queue->toArray());
        self::assertSame(5, $queue->count());
    }

    public function test_that_is_empty_returns_true_for_empty_and_false_when_items_exist(): void
    {
        $queue = ArrayQueue::of('int');

        self::assertTrue($queue->isEmpty());

        $queue->enqueue(1);

        self::assertFalse($queue->isEmpty());
    }

    public function test_that_count_returns_the_correct_count(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame(2, $queue->count());
    }

    public function test_that_to_array_returns_items_in_fifo_order(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame([1, 2, 3], $queue->toArray());
    }

    public function test_that_to_json_encodes_items_in_fifo_order(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame('[1,2]', $queue->toJson());
    }

    public function test_that_to_string_returns_the_same_value_as_to_json(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);

        self::assertSame($queue->toJson(), $queue->toString());
    }

    public function test_that_magic_to_string_returns_the_same_value_as_to_json(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);

        self::assertSame($queue->toJson(), (string) $queue);
    }

    public function test_that_foreach_visits_items_in_fifo_order(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        $seen = [];
        foreach ($queue as $item) {
            $seen[] = $item;
        }

        self::assertSame([1, 2, 3], $seen);
    }

    public function test_that_each_iterates_all_items_in_fifo_order(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        $seen = [];
        $queue->each(function (int $item) use (&$seen): void {
            $seen[] = $item;
        });

        self::assertSame([1, 2], $seen);
    }

    public function test_that_map_returns_a_new_queue_with_transformed_values(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame([10, 20, 30], $queue->map(fn(int $v): int => $v * 10, 'int')->toArray());
    }

    public function test_that_filter_returns_a_new_queue_with_only_matching_items(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame([2, 3], $queue->filter(fn(int $v): bool => $v > 1)->toArray());
    }

    public function test_that_reject_returns_items_not_matching_the_predicate(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame([1], $queue->reject(fn(int $v): bool => $v > 1)->toArray());
    }

    public function test_that_reduce_accumulates_to_a_single_value(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame(6, $queue->reduce(fn(int $acc, int $v): int => $acc + $v, 0));
    }

    public function test_that_sum_returns_correct_value(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(10);
        $queue->enqueue(20);

        self::assertSame(30, $queue->sum());
    }

    public function test_that_sum_returns_null_for_empty_queue(): void
    {
        self::assertNull(ArrayQueue::of('int')->sum());
    }

    public function test_that_average_returns_correct_value(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(10);
        $queue->enqueue(20);

        self::assertSame(15, $queue->average());
    }

    public function test_that_average_returns_null_for_empty_queue(): void
    {
        self::assertNull(ArrayQueue::of('int')->average());
    }

    public function test_that_max_returns_the_greatest_item(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(3);
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame(3, $queue->max());
    }

    public function test_that_max_with_callback_returns_the_item_with_the_greatest_field_value(): void
    {
        $queue = ArrayQueue::of('string');
        $queue->enqueue('hi');
        $queue->enqueue('hello');

        self::assertSame('hello', $queue->max(fn(string $v): int => strlen($v)));
    }

    public function test_that_min_returns_the_smallest_item(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(3);
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame(1, $queue->min());
    }

    public function test_that_min_with_callback_returns_the_item_with_the_smallest_field_value(): void
    {
        $queue = ArrayQueue::of('string');
        $queue->enqueue('hi');
        $queue->enqueue('hello');

        self::assertSame('hi', $queue->min(fn(string $v): int => strlen($v)));
    }

    public function test_that_find_returns_the_first_matching_item_in_fifo_order(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame(2, $queue->find(fn(int $v): bool => $v > 1));
    }

    public function test_that_find_returns_null_when_no_item_matches(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);

        self::assertNull($queue->find(fn(int $v): bool => $v > 99));
    }

    public function test_that_any_returns_true_when_at_least_one_item_matches_and_false_otherwise(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertTrue($queue->any(fn(int $v): bool => $v > 1));
        self::assertFalse($queue->any(fn(int $v): bool => $v > 10));
    }

    public function test_that_every_returns_true_when_all_items_match_and_false_otherwise(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertTrue($queue->every(fn(int $v): bool => $v > 0));
        self::assertFalse($queue->every(fn(int $v): bool => $v > 1));
    }

    public function test_that_partition_splits_items_into_matching_and_non_matching(): void
    {
        $queue = ArrayQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        [$pass, $fail] = $queue->partition(fn(int $v): bool => $v % 2 !== 0);

        self::assertSame([1, 3], $pass->toArray());
        self::assertSame([2], $fail->toArray());
    }
}
