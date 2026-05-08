<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection;

use AssertionError;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Exception\IndexException;
use Fight\Common\Domain\Exception\UnderflowException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArrayList::class)]
class ArrayListTest extends UnitTestCase
{
    public function test_that_of_creates_a_typed_collection_and_item_type_returns_correct_type(): void
    {
        $list = ArrayList::of('string');

        self::assertSame('string', $list->itemType());
    }

    public function test_that_of_with_no_argument_creates_a_dynamic_collection(): void
    {
        $list = ArrayList::of();

        self::assertNull($list->itemType());
    }

    public function test_that_add_appends_an_item_and_count_reflects_the_change(): void
    {
        $list = ArrayList::of('string');
        $list->add('hello');

        self::assertSame(1, $list->count());
    }

    public function test_that_add_throws_for_an_item_of_the_wrong_type(): void
    {
        $list = ArrayList::of('string');

        $this->expectException(AssertionError::class);
        $list->add(42);
    }

    public function test_that_set_replaces_an_item_at_a_positive_index(): void
    {
        $list = ArrayList::of('string');
        $list->add('hello');
        $list->set(0, 'world');

        self::assertSame('world', $list->get(0));
    }

    public function test_that_set_with_negative_index_counts_from_the_end(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->set(-1, 'z');

        self::assertSame('z', $list->get(1));
    }

    public function test_that_set_throws_for_an_out_of_bounds_index(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');

        $this->expectException(IndexException::class);
        $list->set(5, 'x');
    }

    public function test_that_get_returns_the_correct_item_at_a_positive_index(): void
    {
        $list = ArrayList::of('string');
        $list->add('hello');

        self::assertSame('hello', $list->get(0));
    }

    public function test_that_get_with_negative_index_counts_from_the_end(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        self::assertSame('b', $list->get(-1));
    }

    public function test_that_get_throws_for_an_out_of_bounds_index(): void
    {
        $list = ArrayList::of('string');

        $this->expectException(IndexException::class);
        $list->get(0);
    }

    public function test_that_has_returns_true_for_existing_index_and_false_for_out_of_bounds(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');

        self::assertTrue($list->has(0));
        self::assertFalse($list->has(1));
    }

    public function test_that_remove_deletes_an_item_and_reindexes(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');
        $list->remove(1);

        self::assertSame(2, $list->count());
        self::assertSame('c', $list->get(1));
    }

    public function test_that_is_empty_returns_true_for_empty_and_false_when_items_exist(): void
    {
        $list = ArrayList::of('string');

        self::assertTrue($list->isEmpty());

        $list->add('a');

        self::assertFalse($list->isEmpty());
    }

    public function test_that_count_returns_the_correct_count(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        self::assertSame(2, $list->count());
    }

    public function test_that_each_iterates_all_items(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        $visited = [];
        $list->each(function (string $item, int $index) use (&$visited): void {
            $visited[$index] = $item;
        });

        self::assertSame([0 => 'a', 1 => 'b'], $visited);
    }

    public function test_that_map_returns_a_new_collection_with_transformed_values(): void
    {
        $list = ArrayList::of('string');
        $list->add('hello');
        $list->add('world');

        $mapped = $list->map(fn(string $item): string => strtoupper($item), 'string');

        self::assertSame(['HELLO', 'WORLD'], $mapped->toArray());
    }

    public function test_that_filter_returns_a_new_collection_with_only_matching_items(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);
        $list->add(3);

        $filtered = $list->filter(fn(int $item): bool => $item > 1);

        self::assertSame([2, 3], $filtered->toArray());
    }

    public function test_that_reduce_accumulates_to_a_single_value(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);
        $list->add(3);

        $result = $list->reduce(fn(int $acc, int $item): int => $acc + $item, 0);

        self::assertSame(6, $result);
    }

    public function test_that_sum_returns_null_for_empty_collection_and_correct_value_otherwise(): void
    {
        $list = ArrayList::of('int');

        self::assertNull($list->sum());

        $list->add(2);
        $list->add(4);

        self::assertSame(6, $list->sum());
    }

    public function test_that_average_returns_correct_value(): void
    {
        $list = ArrayList::of('int');
        $list->add(2);
        $list->add(4);

        self::assertSame(3, $list->average());
    }

    public function test_that_max_returns_the_item_with_the_greatest_value(): void
    {
        $list = ArrayList::of('int');
        $list->add(3);
        $list->add(1);
        $list->add(4);

        self::assertSame(4, $list->max());
    }

    public function test_that_min_returns_the_item_with_the_smallest_value(): void
    {
        $list = ArrayList::of('int');
        $list->add(3);
        $list->add(1);
        $list->add(4);

        self::assertSame(1, $list->min());
    }

    public function test_that_contains_returns_true_for_present_item_and_false_for_absent(): void
    {
        $list = ArrayList::of('string');
        $list->add('hello');

        self::assertTrue($list->contains('hello'));
        self::assertFalse($list->contains('world'));
    }

    public function test_that_to_array_returns_correct_representation(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        self::assertSame(['a', 'b'], $list->toArray());
    }

    public function test_that_to_json_returns_correct_representation(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        self::assertSame('["a","b"]', $list->toJson());
    }

    public function test_that_to_string_and_magic_to_string_return_correct_representation(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        self::assertSame('["a","b"]', $list->toString());
        self::assertSame('["a","b"]', (string) $list);
    }

    public function test_that_foreach_iteration_visits_all_items_in_order(): void
    {
        $list = ArrayList::of('string');
        $list->add('x');
        $list->add('y');

        $items = [];
        foreach ($list as $index => $item) {
            $items[$index] = $item;
        }

        self::assertSame([0 => 'x', 1 => 'y'], $items);
    }

    public function test_that_sort_returns_a_new_collection_in_ascending_order_using_a_callback(): void
    {
        $list = ArrayList::of('int');
        $list->add(3);
        $list->add(1);
        $list->add(2);

        $sorted = $list->sort(fn(int $a, int $b): int => $a <=> $b);

        self::assertSame([1, 2, 3], $sorted->toArray());
    }

    public function test_that_sort_does_not_modify_the_original_collection(): void
    {
        $list = ArrayList::of('int');
        $list->add(3);
        $list->add(1);
        $list->add(2);

        $list->sort(fn(int $a, int $b): int => $a <=> $b);

        self::assertSame([3, 1, 2], $list->toArray());
    }

    public function test_that_reverse_returns_a_new_collection_in_reversed_order(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');

        $reversed = $list->reverse();

        self::assertSame(['c', 'b', 'a'], $reversed->toArray());
    }

    public function test_that_reverse_does_not_modify_the_original_collection(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');

        $list->reverse();

        self::assertSame(['a', 'b', 'c'], $list->toArray());
    }

    public function test_that_head_returns_the_first_item(): void
    {
        $list = ArrayList::of('string');
        $list->add('first');
        $list->add('second');
        $list->add('third');

        self::assertSame('first', $list->head());
    }

    public function test_that_tail_returns_a_new_collection_without_the_first_item(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');

        $tail = $list->tail();

        self::assertSame(['b', 'c'], $tail->toArray());
    }

    public function test_that_first_with_no_argument_returns_the_first_item(): void
    {
        $list = ArrayList::of('string');
        $list->add('alpha');
        $list->add('beta');

        self::assertSame('alpha', $list->first());
    }

    public function test_that_first_with_a_predicate_returns_the_first_matching_item(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);
        $list->add(3);

        self::assertSame(2, $list->first(fn(int $item): bool => $item > 1));
    }

    public function test_that_first_with_a_predicate_returns_null_when_no_item_matches(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);

        self::assertNull($list->first(fn(int $item): bool => $item > 10));
    }

    public function test_that_last_with_no_argument_returns_the_last_item(): void
    {
        $list = ArrayList::of('string');
        $list->add('alpha');
        $list->add('beta');

        self::assertSame('beta', $list->last());
    }

    public function test_that_last_with_a_predicate_returns_the_last_matching_item(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);
        $list->add(3);

        self::assertSame(2, $list->last(fn(int $item): bool => $item < 3));
    }

    public function test_that_last_with_a_predicate_returns_null_when_no_item_matches(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);

        self::assertNull($list->last(fn(int $item): bool => $item > 10));
    }

    public function test_that_index_of_returns_the_index_of_the_first_matching_item_using_a_closure(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');
        $list->add('b');

        self::assertSame(1, $list->indexOf(fn(string $item): bool => $item === 'b'));
    }

    public function test_that_index_of_returns_null_when_no_item_matches(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        self::assertNull($list->indexOf(fn(string $item): bool => $item === 'z'));
    }

    public function test_that_last_index_of_returns_the_index_of_the_last_matching_item_using_a_closure(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');
        $list->add('b');

        self::assertSame(3, $list->lastIndexOf(fn(string $item): bool => $item === 'b'));
    }

    public function test_that_last_index_of_returns_null_when_no_item_matches(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        self::assertNull($list->lastIndexOf(fn(string $item): bool => $item === 'z'));
    }

    public function test_that_unique_returns_a_new_collection_with_duplicate_items_removed(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('a');
        $list->add('c');

        self::assertSame(['a', 'b', 'c'], $list->unique()->toArray());
    }

    public function test_that_unique_with_a_callback_uses_callback_return_value_for_equality_comparison(): void
    {
        $list = ArrayList::of('string');
        $list->add('hello');
        $list->add('HELLO');
        $list->add('world');

        $unique = $list->unique(fn(string $item): string => strtolower($item));

        self::assertSame(['hello', 'world'], $unique->toArray());
    }

    public function test_that_slice_returns_the_correct_subset_of_items(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');
        $list->add('d');

        self::assertSame(['b', 'c'], $list->slice(1, 2)->toArray());
    }

    public function test_that_page_returns_the_correct_page_of_items(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');
        $list->add('d');

        self::assertSame(['c', 'd'], $list->page(2, 2)->toArray());
    }

    public function test_that_replace_returns_a_new_collection_containing_the_provided_items(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        $replaced = $list->replace(['x', 'y', 'z']);

        self::assertSame(['x', 'y', 'z'], $replaced->toArray());
        self::assertSame(['a', 'b'], $list->toArray());
    }

    public function test_that_length_returns_the_correct_count(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');

        self::assertSame(3, $list->length());
    }

    public function test_that_remove_with_negative_index_removes_from_the_end(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');
        $list->remove(-1);

        self::assertSame(['a', 'b'], $list->toArray());
    }

    public function test_that_remove_with_out_of_bounds_index_is_a_no_op(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->remove(99);

        self::assertSame(1, $list->count());
    }

    public function test_that_array_append_syntax_appends_an_item(): void
    {
        $list = ArrayList::of('string');
        $list[] = 'hello';

        self::assertSame('hello', $list->get(0));
    }

    public function test_that_array_set_syntax_sets_an_item_at_index(): void
    {
        $list = ArrayList::of('string');
        $list->add('old');
        $list[0] = 'new';

        self::assertSame('new', $list->get(0));
    }

    public function test_that_array_get_syntax_returns_item_at_index(): void
    {
        $list = ArrayList::of('string');
        $list->add('hello');

        self::assertSame('hello', $list[0]);
    }

    public function test_that_array_isset_syntax_returns_true_for_existing_index_and_false_for_absent(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');

        self::assertTrue(isset($list[0]));
        self::assertFalse(isset($list[1]));
    }

    public function test_that_array_unset_syntax_removes_the_item_at_the_given_index(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        unset($list[0]);

        self::assertSame(['b'], $list->toArray());
    }

    public function test_that_head_throws_when_collection_is_empty(): void
    {
        $list = ArrayList::of('string');

        $this->expectException(UnderflowException::class);
        $list->head();
    }

    public function test_that_tail_throws_when_collection_is_empty(): void
    {
        $list = ArrayList::of('string');

        $this->expectException(UnderflowException::class);
        $list->tail();
    }

    public function test_that_first_with_predicate_returns_null_for_empty_collection(): void
    {
        $list = ArrayList::of('string');

        self::assertNull($list->first(fn(string $item): bool => $item === 'a'));
    }

    public function test_that_last_with_predicate_returns_null_for_empty_collection(): void
    {
        $list = ArrayList::of('string');

        self::assertNull($list->last(fn(string $item): bool => $item === 'a'));
    }

    public function test_that_index_of_returns_null_for_a_non_closure_value_not_in_the_collection(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');

        self::assertNull($list->indexOf('z'));
    }

    public function test_that_first_with_no_argument_returns_null_for_empty_collection(): void
    {
        $list = ArrayList::of('string');

        self::assertNull($list->first());
    }

    public function test_that_last_with_no_argument_returns_null_for_empty_collection(): void
    {
        $list = ArrayList::of('string');

        self::assertNull($list->last());
    }

    public function test_that_index_of_returns_the_index_of_a_non_closure_value_in_the_collection(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');

        self::assertSame(1, $list->indexOf('b'));
    }

    public function test_that_last_index_of_returns_the_index_of_the_last_occurrence_of_a_non_closure_value(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('b');

        self::assertSame(2, $list->lastIndexOf('b'));
    }

    public function test_that_last_index_of_returns_null_for_a_non_closure_value_not_in_the_collection(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        self::assertNull($list->lastIndexOf('z'));
    }

    public function test_that_key_returns_the_current_index(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        self::assertSame(0, $list->key());
    }

    public function test_that_next_advances_the_cursor_to_the_next_item(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');

        $list->next();

        self::assertSame(1, $list->key());
    }

    public function test_that_rewind_resets_the_cursor_to_the_first_item(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $list->add('c');

        $list->end();
        $list->rewind();

        self::assertSame(0, $list->key());
    }

    public function test_that_current_returns_null_when_the_cursor_is_exhausted(): void
    {
        $list = ArrayList::of('string');

        self::assertNull($list->current());
    }

    public function test_that_max_with_callback_returns_the_item_with_the_greatest_field_value(): void
    {
        $list = ArrayList::of('string');
        $list->add('hi');
        $list->add('hello');
        $list->add('hey');

        self::assertSame('hello', $list->max(fn(string $item): int => strlen($item)));
    }

    public function test_that_min_with_callback_returns_the_item_with_the_smallest_field_value(): void
    {
        $list = ArrayList::of('string');
        $list->add('hi');
        $list->add('hello');
        $list->add('hey');

        self::assertSame('hi', $list->min(fn(string $item): int => strlen($item)));
    }

    public function test_that_average_returns_null_for_empty_collection(): void
    {
        $list = ArrayList::of('int');

        self::assertNull($list->average());
    }

    public function test_that_find_returns_the_first_item_matching_the_predicate(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);
        $list->add(3);

        self::assertSame(2, $list->find(fn(int $item): bool => $item === 2));
    }

    public function test_that_find_returns_null_when_no_item_matches_the_predicate(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);

        self::assertNull($list->find(fn(int $item): bool => $item === 99));
    }

    public function test_that_reject_returns_a_new_collection_with_items_not_matching_the_predicate(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);
        $list->add(3);

        self::assertSame([1], $list->reject(fn(int $item): bool => $item > 1)->toArray());
    }

    public function test_that_any_returns_true_when_at_least_one_item_matches_and_false_otherwise(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);
        $list->add(3);

        self::assertTrue($list->any(fn(int $item): bool => $item > 2));
        self::assertFalse($list->any(fn(int $item): bool => $item > 10));
    }

    public function test_that_every_returns_true_when_all_items_match_and_false_otherwise(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);
        $list->add(3);

        self::assertTrue($list->every(fn(int $item): bool => $item > 0));
        self::assertFalse($list->every(fn(int $item): bool => $item > 1));
    }

    public function test_that_partition_splits_the_collection_into_matching_and_non_matching_items(): void
    {
        $list = ArrayList::of('int');
        $list->add(1);
        $list->add(2);
        $list->add(3);
        $list->add(4);

        [$evens, $odds] = $list->partition(fn(int $item): bool => $item % 2 === 0);

        self::assertSame([2, 4], $evens->toArray());
        self::assertSame([1, 3], $odds->toArray());
    }
}
