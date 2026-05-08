<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection;

use AssertionError;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HashSet::class)]
class HashSetTest extends UnitTestCase
{
    public function test_that_of_creates_a_typed_collection_and_item_type_returns_correct_type(): void
    {
        $set = HashSet::of('int');

        self::assertSame('int', $set->itemType());
    }

    public function test_that_of_with_no_argument_creates_a_dynamic_collection(): void
    {
        $set = HashSet::of();

        self::assertNull($set->itemType());
    }

    public function test_that_add_appends_an_item_contains_returns_true_and_count_reflects_the_change(): void
    {
        $set = HashSet::of('string');
        $set->add('hello');

        self::assertTrue($set->contains('hello'));
        self::assertSame(1, $set->count());
    }

    public function test_that_add_is_idempotent_for_duplicate_items(): void
    {
        $set = HashSet::of('string');
        $set->add('hello');
        $set->add('hello');

        self::assertSame(1, $set->count());
    }

    public function test_that_add_throws_for_an_item_of_the_wrong_type(): void
    {
        $set = HashSet::of('string');

        $this->expectException(AssertionError::class);
        $set->add(42);
    }

    public function test_that_contains_returns_false_for_an_absent_item(): void
    {
        $set = HashSet::of('string');

        self::assertFalse($set->contains('missing'));
    }

    public function test_that_remove_deletes_an_item_and_contains_returns_false(): void
    {
        $set = HashSet::of('string');
        $set->add('hello');
        $set->remove('hello');

        self::assertFalse($set->contains('hello'));
        self::assertSame(0, $set->count());
    }

    public function test_that_remove_of_absent_item_is_a_no_op(): void
    {
        $set = HashSet::of('string');
        $set->add('hello');
        $set->remove('world');

        self::assertSame(1, $set->count());
    }

    public function test_that_difference_returns_items_in_either_set_but_not_both(): void
    {
        $a = HashSet::of('int');
        $a->add(1);
        $a->add(2);
        $a->add(3);

        $b = HashSet::of('int');
        $b->add(2);
        $b->add(3);
        $b->add(4);

        $result = $a->difference($b)->toArray();
        sort($result);

        self::assertSame([1, 4], $result);
    }

    public function test_that_difference_with_same_instance_returns_empty_set(): void
    {
        $a = HashSet::of('int');
        $a->add(1);

        self::assertSame(0, $a->difference($a)->count());
    }

    public function test_that_intersection_returns_items_present_in_both_sets(): void
    {
        $a = HashSet::of('int');
        $a->add(1);
        $a->add(2);
        $a->add(3);

        $b = HashSet::of('int');
        $b->add(2);
        $b->add(3);
        $b->add(4);

        $result = $a->intersection($b)->toArray();
        sort($result);

        self::assertSame([2, 3], $result);
    }

    public function test_that_complement_returns_items_in_other_set_not_in_this_one(): void
    {
        $a = HashSet::of('int');
        $a->add(1);
        $a->add(2);

        $b = HashSet::of('int');
        $b->add(2);
        $b->add(3);
        $b->add(4);

        $result = $a->complement($b)->toArray();
        sort($result);

        self::assertSame([3, 4], $result);
    }

    public function test_that_complement_with_same_instance_returns_empty_set(): void
    {
        $a = HashSet::of('int');
        $a->add(1);

        self::assertSame(0, $a->complement($a)->count());
    }

    public function test_that_union_returns_all_items_from_both_sets_without_duplicates(): void
    {
        $a = HashSet::of('int');
        $a->add(1);
        $a->add(2);

        $b = HashSet::of('int');
        $b->add(2);
        $b->add(3);

        $result = $a->union($b)->toArray();
        sort($result);

        self::assertSame([1, 2, 3], $result);
    }

    public function test_that_each_iterates_all_items(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);

        $seen = [];
        $set->each(function (int $item) use (&$seen): void {
            $seen[] = $item;
        });
        sort($seen);

        self::assertSame([1, 2, 3], $seen);
    }

    public function test_that_map_returns_a_new_collection_with_transformed_values(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);

        $result = $set->map(fn(int $item): int => $item * 2, 'int')->toArray();
        sort($result);

        self::assertSame([2, 4, 6], $result);
    }

    public function test_that_filter_returns_a_new_collection_with_only_matching_items(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);

        $result = $set->filter(fn(int $item): bool => $item > 1)->toArray();
        sort($result);

        self::assertSame([2, 3], $result);
    }

    public function test_that_reduce_accumulates_to_a_single_value(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);

        self::assertSame(6, $set->reduce(fn(int $acc, int $item): int => $acc + $item, 0));
    }

    public function test_that_sum_returns_correct_value(): void
    {
        $set = HashSet::of('int');
        $set->add(2);
        $set->add(4);

        self::assertSame(6, $set->sum());
    }

    public function test_that_sum_returns_null_for_empty_collection(): void
    {
        self::assertNull(HashSet::of('int')->sum());
    }

    public function test_that_average_returns_correct_value(): void
    {
        $set = HashSet::of('int');
        $set->add(2);
        $set->add(4);

        self::assertSame(3, $set->average());
    }

    public function test_that_average_returns_null_for_empty_collection(): void
    {
        self::assertNull(HashSet::of('int')->average());
    }

    public function test_that_max_returns_the_item_with_the_greatest_value(): void
    {
        $set = HashSet::of('int');
        $set->add(3);
        $set->add(1);
        $set->add(4);

        self::assertSame(4, $set->max());
    }

    public function test_that_max_with_callback_returns_the_item_with_the_greatest_field_value(): void
    {
        $set = HashSet::of('string');
        $set->add('hi');
        $set->add('hello');
        $set->add('hey');

        self::assertSame('hello', $set->max(fn(string $item): int => strlen($item)));
    }

    public function test_that_min_returns_the_item_with_the_smallest_value(): void
    {
        $set = HashSet::of('int');
        $set->add(3);
        $set->add(1);
        $set->add(4);

        self::assertSame(1, $set->min());
    }

    public function test_that_min_with_callback_returns_the_item_with_the_smallest_field_value(): void
    {
        $set = HashSet::of('string');
        $set->add('hi');
        $set->add('hello');
        $set->add('hey');

        self::assertSame('hi', $set->min(fn(string $item): int => strlen($item)));
    }

    public function test_that_find_returns_the_first_matching_item(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);

        $result = $set->find(fn(int $item): bool => $item > 1);

        self::assertGreaterThan(1, $result);
    }

    public function test_that_find_returns_null_when_no_item_matches(): void
    {
        $set = HashSet::of('int');
        $set->add(1);

        self::assertNull($set->find(fn(int $item): bool => $item > 99));
    }

    public function test_that_reject_returns_items_not_matching_the_predicate(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);

        $result = $set->reject(fn(int $item): bool => $item > 1)->toArray();
        sort($result);

        self::assertSame([1], $result);
    }

    public function test_that_any_returns_true_when_at_least_one_item_matches_and_false_otherwise(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);

        self::assertTrue($set->any(fn(int $item): bool => $item > 2));
        self::assertFalse($set->any(fn(int $item): bool => $item > 10));
    }

    public function test_that_every_returns_true_when_all_items_match_and_false_otherwise(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);

        self::assertTrue($set->every(fn(int $item): bool => $item > 0));
        self::assertFalse($set->every(fn(int $item): bool => $item > 1));
    }

    public function test_that_partition_splits_the_collection_into_matching_and_non_matching_items(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);
        $set->add(4);

        [$evens, $odds] = $set->partition(fn(int $item): bool => $item % 2 === 0);

        $evenResult = $evens->toArray();
        $oddResult = $odds->toArray();
        sort($evenResult);
        sort($oddResult);

        self::assertSame([2, 4], $evenResult);
        self::assertSame([1, 3], $oddResult);
    }

    public function test_that_is_empty_returns_true_for_empty_and_false_when_items_exist(): void
    {
        $set = HashSet::of('string');

        self::assertTrue($set->isEmpty());

        $set->add('a');

        self::assertFalse($set->isEmpty());
    }

    public function test_that_count_returns_the_correct_count(): void
    {
        $set = HashSet::of('string');
        $set->add('a');
        $set->add('b');

        self::assertSame(2, $set->count());
    }

    public function test_that_to_array_returns_all_items(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);

        $result = $set->toArray();
        sort($result);

        self::assertSame([1, 2, 3], $result);
    }

    public function test_that_to_json_returns_a_json_encoded_array_of_all_items(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);

        $decoded = json_decode($set->toJson(), true);
        sort($decoded);

        self::assertSame([1, 2], $decoded);
    }

    public function test_that_to_string_returns_the_same_value_as_to_json(): void
    {
        $set = HashSet::of('int');
        $set->add(1);

        self::assertSame($set->toJson(), $set->toString());
    }

    public function test_that_magic_to_string_returns_the_same_value_as_to_json(): void
    {
        $set = HashSet::of('int');
        $set->add(1);

        self::assertSame($set->toJson(), (string) $set);
    }

    public function test_that_clone_produces_an_independent_copy(): void
    {
        $original = HashSet::of('string');
        $original->add('a');

        $clone = clone $original;
        $clone->add('b');

        self::assertTrue($original->contains('a'));
        self::assertFalse($original->contains('b'));
        self::assertTrue($clone->contains('a'));
        self::assertTrue($clone->contains('b'));
    }

    public function test_that_foreach_iteration_visits_all_items(): void
    {
        $set = HashSet::of('int');
        $set->add(1);
        $set->add(2);
        $set->add(3);

        $seen = [];
        foreach ($set as $item) {
            $seen[] = $item;
        }
        sort($seen);

        self::assertSame([1, 2, 3], $seen);
    }
}
