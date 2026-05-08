<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection;

use AssertionError;
use Fight\Common\Domain\Collection\LinkedQueue;
use Fight\Common\Domain\Exception\UnderflowException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LinkedQueue::class)]
class LinkedQueueTest extends UnitTestCase
{
    public function test_that_of_creates_a_typed_collection_and_item_type_returns_the_correct_type(): void
    {
        $queue = LinkedQueue::of('string');

        self::assertSame('string', $queue->itemType());
    }

    public function test_that_of_with_no_argument_creates_a_dynamic_collection(): void
    {
        $queue = LinkedQueue::of();

        self::assertNull($queue->itemType());
    }

    public function test_that_enqueue_adds_an_item_and_count_reflects_the_change(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame(2, $queue->count());
    }

    public function test_that_enqueue_throws_for_wrong_type(): void
    {
        $queue = LinkedQueue::of('int');

        $this->expectException(AssertionError::class);
        $queue->enqueue('not-an-int');
    }

    public function test_that_dequeue_removes_and_returns_items_in_fifo_order(): void
    {
        $queue = LinkedQueue::of('int');
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
        LinkedQueue::of('int')->dequeue();
    }

    public function test_that_front_returns_the_next_item_without_removing_it(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame(1, $queue->front());
        self::assertSame(2, $queue->count());
    }

    public function test_that_front_throws_underflow_exception_on_an_empty_queue(): void
    {
        $this->expectException(UnderflowException::class);
        LinkedQueue::of('int')->front();
    }

    public function test_that_many_enqueues_and_dequeues_maintain_fifo_order(): void
    {
        $queue = LinkedQueue::of('int');

        for ($i = 1; $i <= 20; $i++) {
            $queue->enqueue($i);
        }

        for ($i = 1; $i <= 10; $i++) {
            self::assertSame($i, $queue->dequeue());
        }

        for ($i = 21; $i <= 30; $i++) {
            $queue->enqueue($i);
        }

        self::assertSame(range(11, 30), $queue->toArray());
    }

    public function test_that_is_empty_returns_true_for_empty_and_false_when_items_exist(): void
    {
        $queue = LinkedQueue::of('int');

        self::assertTrue($queue->isEmpty());

        $queue->enqueue(1);

        self::assertFalse($queue->isEmpty());
    }

    public function test_that_count_returns_the_correct_count(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame(2, $queue->count());
    }

    public function test_that_to_array_returns_items_in_fifo_order(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame([1, 2, 3], $queue->toArray());
    }

    public function test_that_to_json_encodes_items_in_fifo_order(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame('[1,2]', $queue->toJson());
    }

    public function test_that_to_string_returns_the_same_value_as_to_json(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);

        self::assertSame($queue->toJson(), $queue->toString());
    }

    public function test_that_magic_to_string_returns_the_same_value_as_to_json(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);

        self::assertSame($queue->toJson(), (string) $queue);
    }

    public function test_that_foreach_visits_items_in_fifo_order(): void
    {
        $queue = LinkedQueue::of('int');
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
        $queue = LinkedQueue::of('int');
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
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame([10, 20, 30], $queue->map(fn(int $v): int => $v * 10, 'int')->toArray());
    }

    public function test_that_filter_returns_a_new_queue_with_only_matching_items(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame([2, 3], $queue->filter(fn(int $v): bool => $v > 1)->toArray());
    }

    public function test_that_reject_returns_items_not_matching_the_predicate(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame([1], $queue->reject(fn(int $v): bool => $v > 1)->toArray());
    }

    public function test_that_reduce_accumulates_to_a_single_value(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame(6, $queue->reduce(fn(int $acc, int $v): int => $acc + $v, 0));
    }

    public function test_that_sum_returns_correct_value(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(10);
        $queue->enqueue(20);

        self::assertSame(30, $queue->sum());
    }

    public function test_that_sum_returns_null_for_empty_queue(): void
    {
        self::assertNull(LinkedQueue::of('int')->sum());
    }

    public function test_that_average_returns_correct_value(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(10);
        $queue->enqueue(20);

        self::assertSame(15, $queue->average());
    }

    public function test_that_average_returns_null_for_empty_queue(): void
    {
        self::assertNull(LinkedQueue::of('int')->average());
    }

    public function test_that_max_returns_the_greatest_item(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(3);
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame(3, $queue->max());
    }

    public function test_that_max_with_callback_returns_the_item_with_the_greatest_field_value(): void
    {
        $queue = LinkedQueue::of('string');
        $queue->enqueue('hi');
        $queue->enqueue('hello');

        self::assertSame('hello', $queue->max(fn(string $v): int => strlen($v)));
    }

    public function test_that_min_returns_the_smallest_item(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(3);
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertSame(1, $queue->min());
    }

    public function test_that_min_with_callback_returns_the_item_with_the_smallest_field_value(): void
    {
        $queue = LinkedQueue::of('string');
        $queue->enqueue('hi');
        $queue->enqueue('hello');

        self::assertSame('hi', $queue->min(fn(string $v): int => strlen($v)));
    }

    public function test_that_find_returns_the_first_matching_item_in_fifo_order(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        self::assertSame(2, $queue->find(fn(int $v): bool => $v > 1));
    }

    public function test_that_find_returns_null_when_no_item_matches(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);

        self::assertNull($queue->find(fn(int $v): bool => $v > 99));
    }

    public function test_that_any_returns_true_when_at_least_one_item_matches_and_false_otherwise(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertTrue($queue->any(fn(int $v): bool => $v > 1));
        self::assertFalse($queue->any(fn(int $v): bool => $v > 10));
    }

    public function test_that_every_returns_true_when_all_items_match_and_false_otherwise(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);

        self::assertTrue($queue->every(fn(int $v): bool => $v > 0));
        self::assertFalse($queue->every(fn(int $v): bool => $v > 1));
    }

    public function test_that_partition_splits_items_into_matching_and_non_matching(): void
    {
        $queue = LinkedQueue::of('int');
        $queue->enqueue(1);
        $queue->enqueue(2);
        $queue->enqueue(3);

        [$pass, $fail] = $queue->partition(fn(int $v): bool => $v % 2 !== 0);

        self::assertSame([1, 3], $pass->toArray());
        self::assertSame([2], $fail->toArray());
    }

    public function test_that_clone_produces_an_independent_copy(): void
    {
        $original = LinkedQueue::of('int');
        $original->enqueue(1);

        $clone = clone $original;
        $clone->enqueue(2);

        self::assertSame([1], $original->toArray());
        self::assertSame([1, 2], $clone->toArray());
    }
}
