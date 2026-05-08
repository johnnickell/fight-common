<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection;

use AssertionError;
use Fight\Common\Domain\Collection\Comparison\IntegerComparator;
use Fight\Common\Domain\Collection\Comparison\StringComparator;
use Fight\Common\Domain\Collection\SortedSet;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Exception\UnderflowException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SortedSet::class)]
class SortedSetTest extends UnitTestCase
{
    public function test_that_integer_factory_creates_an_int_typed_set(): void
    {
        $set = SortedSet::integer();

        self::assertSame('int', $set->itemType());
    }

    public function test_that_float_factory_creates_a_float_typed_set(): void
    {
        $set = SortedSet::float();

        self::assertSame('float', $set->itemType());
    }

    public function test_that_string_factory_creates_a_string_typed_set(): void
    {
        $set = SortedSet::string();

        self::assertSame('string', $set->itemType());
    }

    public function test_that_comparable_factory_creates_a_typed_set_for_comparable_items(): void
    {
        $set = SortedSet::comparable(StringObject::class);

        self::assertSame(StringObject::class, $set->itemType());
    }

    public function test_that_callback_factory_creates_a_set_with_custom_ordering(): void
    {
        $set = SortedSet::callback(fn(string $a, string $b): int => strcmp($a, $b), 'string');
        $set->add('banana');
        $set->add('apple');

        self::assertSame(['apple', 'banana'], $set->toArray());
    }

    public function test_that_create_factory_creates_a_set_with_the_given_comparator(): void
    {
        $set = SortedSet::create(new IntegerComparator(), 'int');

        self::assertSame('int', $set->itemType());
    }

    public function test_that_create_factory_with_no_item_type_creates_a_dynamic_set(): void
    {
        $set = SortedSet::create(new IntegerComparator());

        self::assertNull($set->itemType());
    }

    public function test_that_add_inserts_items_in_sorted_order(): void
    {
        $set = SortedSet::integer();
        $set->add(3);
        $set->add(1);
        $set->add(2);

        self::assertSame([1, 2, 3], $set->toArray());
    }

    public function test_that_add_is_idempotent_for_duplicate_items(): void
    {
        $set = SortedSet::integer();
        $set->add(5);
        $set->add(5);

        self::assertSame(1, $set->count());
    }

    public function test_that_add_throws_for_an_item_of_the_wrong_type(): void
    {
        $set = SortedSet::integer();

        $this->expectException(AssertionError::class);
        $set->add('not-an-int');
    }

    public function test_that_contains_returns_true_for_a_present_item_and_false_for_an_absent_one(): void
    {
        $set = SortedSet::integer();
        $set->add(10);

        self::assertTrue($set->contains(10));
        self::assertFalse($set->contains(99));
    }

    public function test_that_remove_deletes_an_item(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);
        $set->remove(1);

        self::assertFalse($set->contains(1));
        self::assertSame(1, $set->count());
    }

    public function test_that_remove_min_removes_the_smallest_item(): void
    {
        $set = SortedSet::integer();
        $set->add(3);
        $set->add(1);
        $set->add(2);
        $set->removeMin();

        self::assertSame([2, 3], $set->toArray());
    }

    public function test_that_remove_min_with_callback_removes_the_item_with_the_smallest_field_value(): void
    {
        $set = SortedSet::string();
        $set->add('hi');
        $set->add('hello');
        $set->add('hey');
        $set->removeMin(fn(string $item): int => strlen($item));

        self::assertFalse($set->contains('hi'));
    }

    public function test_that_remove_min_throws_for_an_empty_set(): void
    {
        $set = SortedSet::integer();

        $this->expectException(UnderflowException::class);
        $set->removeMin();
    }

    public function test_that_remove_max_removes_the_largest_item(): void
    {
        $set = SortedSet::integer();
        $set->add(3);
        $set->add(1);
        $set->add(2);
        $set->removeMax();

        self::assertSame([1, 2], $set->toArray());
    }

    public function test_that_remove_max_with_callback_removes_the_item_with_the_largest_field_value(): void
    {
        $set = SortedSet::string();
        $set->add('hi');
        $set->add('hello');
        $set->add('hey');
        $set->removeMax(fn(string $item): int => strlen($item));

        self::assertFalse($set->contains('hello'));
    }

    public function test_that_remove_max_throws_for_an_empty_set(): void
    {
        $set = SortedSet::integer();

        $this->expectException(UnderflowException::class);
        $set->removeMax();
    }

    public function test_that_min_returns_the_smallest_item(): void
    {
        $set = SortedSet::integer();
        $set->add(3);
        $set->add(1);
        $set->add(2);

        self::assertSame(1, $set->min());
    }

    public function test_that_min_with_callback_returns_the_item_with_the_smallest_field_value(): void
    {
        $set = SortedSet::string();
        $set->add('hi');
        $set->add('hello');
        $set->add('hey');

        self::assertSame('hi', $set->min(fn(string $item): int => strlen($item)));
    }

    public function test_that_min_throws_for_an_empty_set(): void
    {
        $set = SortedSet::integer();

        $this->expectException(UnderflowException::class);
        $set->min();
    }

    public function test_that_max_returns_the_largest_item(): void
    {
        $set = SortedSet::integer();
        $set->add(3);
        $set->add(1);
        $set->add(2);

        self::assertSame(3, $set->max());
    }

    public function test_that_max_with_callback_returns_the_item_with_the_largest_field_value(): void
    {
        $set = SortedSet::string();
        $set->add('hi');
        $set->add('hello');
        $set->add('hey');

        self::assertSame('hello', $set->max(fn(string $item): int => strlen($item)));
    }

    public function test_that_max_throws_for_an_empty_set(): void
    {
        $set = SortedSet::integer();

        $this->expectException(UnderflowException::class);
        $set->max();
    }

    public function test_that_floor_returns_the_largest_item_less_than_or_equal_to_the_given_value(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(3);
        $set->add(5);

        self::assertSame(3, $set->floor(4));
    }

    public function test_that_ceiling_returns_the_smallest_item_greater_than_or_equal_to_the_given_value(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(3);
        $set->add(5);

        self::assertSame(3, $set->ceiling(2));
    }

    public function test_that_rank_returns_the_number_of_items_less_than_the_given_value(): void
    {
        $set = SortedSet::integer();
        $set->add(10);
        $set->add(20);
        $set->add(30);

        self::assertSame(1, $set->rank(20));
    }

    public function test_that_select_returns_the_item_at_the_given_rank(): void
    {
        $set = SortedSet::integer();
        $set->add(10);
        $set->add(20);
        $set->add(30);

        self::assertSame(20, $set->select(1));
    }

    public function test_that_range_returns_items_between_two_values_inclusive(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);
        $set->add(3);
        $set->add(4);
        $set->add(5);

        $result = [];
        foreach ($set->range(2, 4) as $item) {
            $result[] = $item;
        }

        self::assertSame([2, 3, 4], $result);
    }

    public function test_that_range_count_returns_the_count_of_items_between_two_values(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);
        $set->add(3);
        $set->add(4);
        $set->add(5);

        self::assertSame(3, $set->rangeCount(2, 4));
    }

    public function test_that_difference_returns_items_in_either_set_but_not_both(): void
    {
        $a = SortedSet::integer();
        $a->add(1);
        $a->add(2);
        $a->add(3);

        $b = SortedSet::integer();
        $b->add(2);
        $b->add(3);
        $b->add(4);

        self::assertSame([1, 4], $a->difference($b)->toArray());
    }

    public function test_that_difference_with_same_instance_returns_empty_set(): void
    {
        $a = SortedSet::integer();
        $a->add(1);

        self::assertSame(0, $a->difference($a)->count());
    }

    public function test_that_intersection_returns_items_present_in_both_sets(): void
    {
        $a = SortedSet::integer();
        $a->add(1);
        $a->add(2);
        $a->add(3);

        $b = SortedSet::integer();
        $b->add(2);
        $b->add(3);
        $b->add(4);

        self::assertSame([2, 3], $a->intersection($b)->toArray());
    }

    public function test_that_complement_returns_items_in_other_set_not_in_this_one(): void
    {
        $a = SortedSet::integer();
        $a->add(1);
        $a->add(2);

        $b = SortedSet::integer();
        $b->add(2);
        $b->add(3);
        $b->add(4);

        self::assertSame([3, 4], $a->complement($b)->toArray());
    }

    public function test_that_complement_with_same_instance_returns_empty_set(): void
    {
        $a = SortedSet::integer();
        $a->add(1);

        self::assertSame(0, $a->complement($a)->count());
    }

    public function test_that_union_returns_all_items_from_both_sets_without_duplicates(): void
    {
        $a = SortedSet::integer();
        $a->add(1);
        $a->add(2);

        $b = SortedSet::integer();
        $b->add(2);
        $b->add(3);

        self::assertSame([1, 2, 3], $a->union($b)->toArray());
    }

    public function test_that_each_iterates_all_items_in_sorted_order(): void
    {
        $set = SortedSet::integer();
        $set->add(3);
        $set->add(1);
        $set->add(2);

        $seen = [];
        $set->each(function (int $item) use (&$seen): void {
            $seen[] = $item;
        });

        self::assertSame([1, 2, 3], $seen);
    }

    public function test_that_map_returns_a_new_set_with_transformed_values(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);
        $set->add(3);

        $result = $set->map(fn(int $item): int => $item * 2, new IntegerComparator(), 'int');

        self::assertSame([2, 4, 6], $result->toArray());
    }

    public function test_that_filter_returns_a_new_set_with_only_matching_items(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);
        $set->add(3);

        $result = $set->filter(fn(int $item): bool => $item > 1);

        self::assertSame([2, 3], $result->toArray());
    }

    public function test_that_reject_returns_items_not_matching_the_predicate(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);
        $set->add(3);

        $result = $set->reject(fn(int $item): bool => $item > 1);

        self::assertSame([1], $result->toArray());
    }

    public function test_that_reduce_accumulates_to_a_single_value(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);
        $set->add(3);

        self::assertSame(6, $set->reduce(fn(int $acc, int $item): int => $acc + $item, 0));
    }

    public function test_that_sum_returns_correct_value(): void
    {
        $set = SortedSet::integer();
        $set->add(10);
        $set->add(20);

        self::assertSame(30, $set->sum());
    }

    public function test_that_sum_returns_null_for_an_empty_set(): void
    {
        self::assertNull(SortedSet::integer()->sum());
    }

    public function test_that_average_returns_correct_value(): void
    {
        $set = SortedSet::integer();
        $set->add(10);
        $set->add(20);

        self::assertSame(15, $set->average());
    }

    public function test_that_average_returns_null_for_an_empty_set(): void
    {
        self::assertNull(SortedSet::integer()->average());
    }

    public function test_that_find_returns_the_first_matching_item(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);
        $set->add(3);

        self::assertSame(2, $set->find(fn(int $item): bool => $item > 1));
    }

    public function test_that_find_returns_null_when_no_item_matches(): void
    {
        $set = SortedSet::integer();
        $set->add(1);

        self::assertNull($set->find(fn(int $item): bool => $item > 99));
    }

    public function test_that_any_returns_true_when_at_least_one_item_matches_and_false_otherwise(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);

        self::assertTrue($set->any(fn(int $item): bool => $item > 1));
        self::assertFalse($set->any(fn(int $item): bool => $item > 10));
    }

    public function test_that_every_returns_true_when_all_items_match_and_false_otherwise(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);

        self::assertTrue($set->every(fn(int $item): bool => $item > 0));
        self::assertFalse($set->every(fn(int $item): bool => $item > 1));
    }

    public function test_that_partition_splits_the_set_into_matching_and_non_matching_items(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);
        $set->add(3);
        $set->add(4);

        [$evens, $odds] = $set->partition(fn(int $item): bool => $item % 2 === 0);

        self::assertSame([2, 4], $evens->toArray());
        self::assertSame([1, 3], $odds->toArray());
    }

    public function test_that_is_empty_returns_true_for_empty_and_false_when_items_exist(): void
    {
        $set = SortedSet::integer();

        self::assertTrue($set->isEmpty());

        $set->add(1);

        self::assertFalse($set->isEmpty());
    }

    public function test_that_count_returns_the_correct_count(): void
    {
        $set = SortedSet::integer();
        $set->add(1);
        $set->add(2);

        self::assertSame(2, $set->count());
    }

    public function test_that_to_array_returns_all_items_in_sorted_order(): void
    {
        $set = SortedSet::integer();
        $set->add(3);
        $set->add(1);
        $set->add(2);

        self::assertSame([1, 2, 3], $set->toArray());
    }

    public function test_that_to_json_returns_a_json_encoded_array_of_all_items(): void
    {
        $set = SortedSet::integer();
        $set->add(3);
        $set->add(1);
        $set->add(2);

        self::assertSame('[1,2,3]', $set->toJson());
    }

    public function test_that_to_string_returns_the_same_value_as_to_json(): void
    {
        $set = SortedSet::integer();
        $set->add(1);

        self::assertSame($set->toJson(), $set->toString());
    }

    public function test_that_magic_to_string_returns_the_same_value_as_to_json(): void
    {
        $set = SortedSet::integer();
        $set->add(1);

        self::assertSame($set->toJson(), (string) $set);
    }

    public function test_that_clone_produces_an_independent_copy(): void
    {
        $original = SortedSet::integer();
        $original->add(1);

        $clone = clone $original;
        $clone->add(2);

        self::assertTrue($original->contains(1));
        self::assertFalse($original->contains(2));
        self::assertTrue($clone->contains(1));
        self::assertTrue($clone->contains(2));
    }

    public function test_that_foreach_visits_all_items_in_sorted_order(): void
    {
        $set = SortedSet::integer();
        $set->add(3);
        $set->add(1);
        $set->add(2);

        $seen = [];
        foreach ($set as $item) {
            $seen[] = $item;
        }

        self::assertSame([1, 2, 3], $seen);
    }
}
