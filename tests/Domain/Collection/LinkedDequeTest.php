<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection;

use AssertionError;
use Fight\Common\Domain\Collection\LinkedDeque;
use Fight\Common\Domain\Exception\UnderflowException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LinkedDeque::class)]
class LinkedDequeTest extends UnitTestCase
{
    public function test_that_of_creates_a_typed_collection_and_item_type_returns_the_correct_type(): void
    {
        $deque = LinkedDeque::of('string');

        self::assertSame('string', $deque->itemType());
    }

    public function test_that_of_with_no_argument_creates_a_dynamic_collection(): void
    {
        $deque = LinkedDeque::of();

        self::assertNull($deque->itemType());
    }

    public function test_that_add_first_inserts_at_the_front(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(2);
        $deque->addFirst(1);

        self::assertSame([1, 2], $deque->toArray());
    }

    public function test_that_add_last_inserts_at_the_back(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addFirst(1);
        $deque->addLast(2);

        self::assertSame([1, 2], $deque->toArray());
    }

    public function test_that_add_first_throws_for_wrong_type(): void
    {
        $deque = LinkedDeque::of('int');

        $this->expectException(AssertionError::class);
        $deque->addFirst('not-an-int');
    }

    public function test_that_add_last_throws_for_wrong_type(): void
    {
        $deque = LinkedDeque::of('int');

        $this->expectException(AssertionError::class);
        $deque->addLast('not-an-int');
    }

    public function test_that_remove_first_removes_and_returns_from_the_front(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);
        $deque->addLast(3);

        self::assertSame(1, $deque->removeFirst());
        self::assertSame(2, $deque->removeFirst());
        self::assertSame(1, $deque->count());
    }

    public function test_that_remove_last_removes_and_returns_from_the_back(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);
        $deque->addLast(3);

        self::assertSame(3, $deque->removeLast());
        self::assertSame(2, $deque->removeLast());
        self::assertSame(1, $deque->count());
    }

    public function test_that_both_ends_can_be_mixed_in_deque_order(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(2);
        $deque->addFirst(1);
        $deque->addLast(3);

        self::assertSame(1, $deque->removeFirst());
        self::assertSame(3, $deque->removeLast());
        self::assertSame([2], $deque->toArray());
    }

    public function test_that_remove_first_throws_underflow_exception_on_an_empty_deque(): void
    {
        $this->expectException(UnderflowException::class);
        LinkedDeque::of('int')->removeFirst();
    }

    public function test_that_remove_last_throws_underflow_exception_on_an_empty_deque(): void
    {
        $this->expectException(UnderflowException::class);
        LinkedDeque::of('int')->removeLast();
    }

    public function test_that_first_returns_the_front_item_without_removing_it(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);

        self::assertSame(1, $deque->first());
        self::assertSame(2, $deque->count());
    }

    public function test_that_last_returns_the_back_item_without_removing_it(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);

        self::assertSame(2, $deque->last());
        self::assertSame(2, $deque->count());
    }

    public function test_that_first_throws_underflow_exception_on_an_empty_deque(): void
    {
        $this->expectException(UnderflowException::class);
        LinkedDeque::of('int')->first();
    }

    public function test_that_last_throws_underflow_exception_on_an_empty_deque(): void
    {
        $this->expectException(UnderflowException::class);
        LinkedDeque::of('int')->last();
    }

    public function test_that_is_empty_returns_true_for_empty_and_false_when_items_exist(): void
    {
        $deque = LinkedDeque::of('int');

        self::assertTrue($deque->isEmpty());

        $deque->addLast(1);

        self::assertFalse($deque->isEmpty());
    }

    public function test_that_count_returns_the_correct_count(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addFirst(0);

        self::assertSame(2, $deque->count());
    }

    public function test_that_to_array_returns_items_from_front_to_back(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);
        $deque->addFirst(0);

        self::assertSame([0, 1, 2], $deque->toArray());
    }

    public function test_that_to_json_encodes_items_from_front_to_back(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);

        self::assertSame('[1,2]', $deque->toJson());
    }

    public function test_that_to_string_returns_the_same_value_as_to_json(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);

        self::assertSame($deque->toJson(), $deque->toString());
    }

    public function test_that_magic_to_string_returns_the_same_value_as_to_json(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);

        self::assertSame($deque->toJson(), (string) $deque);
    }

    public function test_that_foreach_visits_items_from_front_to_back(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);
        $deque->addFirst(0);

        $seen = [];
        foreach ($deque as $item) {
            $seen[] = $item;
        }

        self::assertSame([0, 1, 2], $seen);
    }

    public function test_that_each_iterates_all_items_from_front_to_back(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);

        $seen = [];
        $deque->each(function (int $item) use (&$seen): void {
            $seen[] = $item;
        });

        self::assertSame([1, 2], $seen);
    }

    public function test_that_map_returns_a_new_deque_with_transformed_values(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);
        $deque->addLast(3);

        self::assertSame([10, 20, 30], $deque->map(fn(int $v): int => $v * 10, 'int')->toArray());
    }

    public function test_that_filter_returns_a_new_deque_with_only_matching_items(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);
        $deque->addLast(3);

        self::assertSame([2, 3], $deque->filter(fn(int $v): bool => $v > 1)->toArray());
    }

    public function test_that_reject_returns_items_not_matching_the_predicate(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);
        $deque->addLast(3);

        self::assertSame([1], $deque->reject(fn(int $v): bool => $v > 1)->toArray());
    }

    public function test_that_reduce_accumulates_to_a_single_value(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);
        $deque->addLast(3);

        self::assertSame(6, $deque->reduce(fn(int $acc, int $v): int => $acc + $v, 0));
    }

    public function test_that_sum_returns_correct_value(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(10);
        $deque->addLast(20);

        self::assertSame(30, $deque->sum());
    }

    public function test_that_sum_returns_null_for_empty_deque(): void
    {
        self::assertNull(LinkedDeque::of('int')->sum());
    }

    public function test_that_average_returns_correct_value(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(10);
        $deque->addLast(20);

        self::assertSame(15, $deque->average());
    }

    public function test_that_average_returns_null_for_empty_deque(): void
    {
        self::assertNull(LinkedDeque::of('int')->average());
    }

    public function test_that_max_returns_the_greatest_item(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(3);
        $deque->addLast(1);
        $deque->addLast(2);

        self::assertSame(3, $deque->max());
    }

    public function test_that_max_with_callback_returns_the_item_with_the_greatest_field_value(): void
    {
        $deque = LinkedDeque::of('string');
        $deque->addLast('hi');
        $deque->addLast('hello');

        self::assertSame('hello', $deque->max(fn(string $v): int => strlen($v)));
    }

    public function test_that_min_returns_the_smallest_item(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(3);
        $deque->addLast(1);
        $deque->addLast(2);

        self::assertSame(1, $deque->min());
    }

    public function test_that_min_with_callback_returns_the_item_with_the_smallest_field_value(): void
    {
        $deque = LinkedDeque::of('string');
        $deque->addLast('hi');
        $deque->addLast('hello');

        self::assertSame('hi', $deque->min(fn(string $v): int => strlen($v)));
    }

    public function test_that_find_returns_the_first_matching_item(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);
        $deque->addLast(3);

        self::assertSame(2, $deque->find(fn(int $v): bool => $v > 1));
    }

    public function test_that_find_returns_null_when_no_item_matches(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);

        self::assertNull($deque->find(fn(int $v): bool => $v > 99));
    }

    public function test_that_any_returns_true_when_at_least_one_item_matches_and_false_otherwise(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);

        self::assertTrue($deque->any(fn(int $v): bool => $v > 1));
        self::assertFalse($deque->any(fn(int $v): bool => $v > 10));
    }

    public function test_that_every_returns_true_when_all_items_match_and_false_otherwise(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);

        self::assertTrue($deque->every(fn(int $v): bool => $v > 0));
        self::assertFalse($deque->every(fn(int $v): bool => $v > 1));
    }

    public function test_that_partition_splits_items_into_matching_and_non_matching(): void
    {
        $deque = LinkedDeque::of('int');
        $deque->addLast(1);
        $deque->addLast(2);
        $deque->addLast(3);

        [$pass, $fail] = $deque->partition(fn(int $v): bool => $v % 2 !== 0);

        self::assertSame([1, 3], $pass->toArray());
        self::assertSame([2], $fail->toArray());
    }

    public function test_that_clone_produces_an_independent_copy(): void
    {
        $original = LinkedDeque::of('int');
        $original->addLast(1);

        $clone = clone $original;
        $clone->addLast(2);

        self::assertSame([1], $original->toArray());
        self::assertSame([1, 2], $clone->toArray());
    }
}
