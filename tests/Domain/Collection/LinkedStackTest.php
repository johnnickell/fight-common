<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection;

use AssertionError;
use Fight\Common\Domain\Collection\LinkedStack;
use Fight\Common\Domain\Exception\UnderflowException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LinkedStack::class)]
class LinkedStackTest extends UnitTestCase
{
    public function test_that_of_creates_a_typed_collection_and_item_type_returns_the_correct_type(): void
    {
        $stack = LinkedStack::of('int');

        self::assertSame('int', $stack->itemType());
    }

    public function test_that_of_with_no_argument_creates_a_dynamic_collection(): void
    {
        $stack = LinkedStack::of();

        self::assertNull($stack->itemType());
    }

    public function test_that_push_adds_an_item_and_count_reflects_the_change(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);

        self::assertSame(2, $stack->count());
    }

    public function test_that_push_throws_for_wrong_type(): void
    {
        $stack = LinkedStack::of('int');

        $this->expectException(AssertionError::class);
        $stack->push('not-an-int');
    }

    public function test_that_pop_removes_and_returns_the_top_item_in_lifo_order(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);

        self::assertSame(3, $stack->pop());
        self::assertSame(2, $stack->pop());
        self::assertSame(1, $stack->pop());
        self::assertSame(0, $stack->count());
    }

    public function test_that_pop_throws_underflow_exception_on_an_empty_stack(): void
    {
        $this->expectException(UnderflowException::class);
        LinkedStack::of('int')->pop();
    }

    public function test_that_top_returns_the_top_item_without_removing_it(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);

        self::assertSame(2, $stack->top());
        self::assertSame(2, $stack->count());
    }

    public function test_that_top_throws_underflow_exception_on_an_empty_stack(): void
    {
        $this->expectException(UnderflowException::class);
        LinkedStack::of('int')->top();
    }

    public function test_that_is_empty_returns_true_for_empty_and_false_when_items_exist(): void
    {
        $stack = LinkedStack::of('int');

        self::assertTrue($stack->isEmpty());

        $stack->push(1);

        self::assertFalse($stack->isEmpty());
    }

    public function test_that_count_returns_the_correct_count(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);

        self::assertSame(3, $stack->count());
    }

    public function test_that_to_array_returns_items_in_lifo_order(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);

        self::assertSame([3, 2, 1], $stack->toArray());
    }

    public function test_that_to_json_encodes_items_in_lifo_order(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);

        self::assertSame('[2,1]', $stack->toJson());
    }

    public function test_that_to_string_returns_the_same_value_as_to_json(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);

        self::assertSame($stack->toJson(), $stack->toString());
    }

    public function test_that_magic_to_string_returns_the_same_value_as_to_json(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);

        self::assertSame($stack->toJson(), (string) $stack);
    }

    public function test_that_foreach_visits_items_in_lifo_order(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);

        $seen = [];
        foreach ($stack as $item) {
            $seen[] = $item;
        }

        self::assertSame([3, 2, 1], $seen);
    }

    public function test_that_each_iterates_all_items_in_lifo_order(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);

        $seen = [];
        $stack->each(function (int $item) use (&$seen): void {
            $seen[] = $item;
        });

        self::assertSame([2, 1], $seen);
    }

    public function test_that_map_returns_a_new_stack_with_transformed_values_preserving_order(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);

        $mapped = $stack->map(fn(int $v): int => $v * 10, 'int');

        self::assertSame([30, 20, 10], $mapped->toArray());
    }

    public function test_that_filter_returns_a_new_stack_with_only_matching_items(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);

        self::assertSame([3, 2], $stack->filter(fn(int $v): bool => $v > 1)->toArray());
    }

    public function test_that_reject_returns_items_not_matching_the_predicate(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);

        self::assertSame([1], $stack->reject(fn(int $v): bool => $v > 1)->toArray());
    }

    public function test_that_reduce_accumulates_to_a_single_value(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);

        self::assertSame(6, $stack->reduce(fn(int $acc, int $v): int => $acc + $v, 0));
    }

    public function test_that_sum_returns_correct_value(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(10);
        $stack->push(20);

        self::assertSame(30, $stack->sum());
    }

    public function test_that_sum_returns_null_for_empty_stack(): void
    {
        self::assertNull(LinkedStack::of('int')->sum());
    }

    public function test_that_average_returns_correct_value(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(10);
        $stack->push(20);

        self::assertSame(15, $stack->average());
    }

    public function test_that_average_returns_null_for_empty_stack(): void
    {
        self::assertNull(LinkedStack::of('int')->average());
    }

    public function test_that_max_returns_the_greatest_item(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(3);
        $stack->push(1);
        $stack->push(2);

        self::assertSame(3, $stack->max());
    }

    public function test_that_max_with_callback_returns_the_item_with_the_greatest_field_value(): void
    {
        $stack = LinkedStack::of('string');
        $stack->push('hi');
        $stack->push('hello');

        self::assertSame('hello', $stack->max(fn(string $v): int => strlen($v)));
    }

    public function test_that_min_returns_the_smallest_item(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(3);
        $stack->push(1);
        $stack->push(2);

        self::assertSame(1, $stack->min());
    }

    public function test_that_min_with_callback_returns_the_item_with_the_smallest_field_value(): void
    {
        $stack = LinkedStack::of('string');
        $stack->push('hi');
        $stack->push('hello');

        self::assertSame('hi', $stack->min(fn(string $v): int => strlen($v)));
    }

    public function test_that_find_returns_the_first_matching_item_in_lifo_order(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);

        self::assertSame(3, $stack->find(fn(int $v): bool => $v > 1));
    }

    public function test_that_find_returns_null_when_no_item_matches(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);

        self::assertNull($stack->find(fn(int $v): bool => $v > 99));
    }

    public function test_that_any_returns_true_when_at_least_one_item_matches_and_false_otherwise(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);

        self::assertTrue($stack->any(fn(int $v): bool => $v > 1));
        self::assertFalse($stack->any(fn(int $v): bool => $v > 10));
    }

    public function test_that_every_returns_true_when_all_items_match_and_false_otherwise(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);

        self::assertTrue($stack->every(fn(int $v): bool => $v > 0));
        self::assertFalse($stack->every(fn(int $v): bool => $v > 1));
    }

    public function test_that_partition_splits_items_into_matching_and_non_matching(): void
    {
        $stack = LinkedStack::of('int');
        $stack->push(1);
        $stack->push(2);
        $stack->push(3);

        [$pass, $fail] = $stack->partition(fn(int $v): bool => $v % 2 !== 0);

        self::assertSame([3, 1], $pass->toArray());
        self::assertSame([2], $fail->toArray());
    }

    public function test_that_clone_produces_an_independent_copy(): void
    {
        $original = LinkedStack::of('int');
        $original->push(1);

        $clone = clone $original;
        $clone->push(2);

        self::assertSame([1], $original->toArray());
        self::assertSame([2, 1], $clone->toArray());
    }
}
