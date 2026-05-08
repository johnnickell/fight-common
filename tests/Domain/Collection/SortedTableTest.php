<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection;

use AssertionError;
use Fight\Common\Domain\Collection\Comparison\IntegerComparator;
use Fight\Common\Domain\Collection\SortedTable;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Exception\UnderflowException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SortedTable::class)]
class SortedTableTest extends UnitTestCase
{
    public function test_that_integer_factory_creates_an_int_keyed_table(): void
    {
        $table = SortedTable::integer('string');

        self::assertSame('int', $table->keyType());
        self::assertSame('string', $table->valueType());
    }

    public function test_that_integer_factory_with_no_value_type_creates_a_dynamic_value_table(): void
    {
        $table = SortedTable::integer();

        self::assertSame('int', $table->keyType());
        self::assertNull($table->valueType());
    }

    public function test_that_float_factory_creates_a_float_keyed_table(): void
    {
        $table = SortedTable::float('int');

        self::assertSame('float', $table->keyType());
        self::assertSame('int', $table->valueType());
    }

    public function test_that_string_factory_creates_a_string_keyed_table(): void
    {
        $table = SortedTable::string('int');

        self::assertSame('string', $table->keyType());
        self::assertSame('int', $table->valueType());
    }

    public function test_that_comparable_factory_creates_a_typed_table_for_comparable_keys(): void
    {
        $table = SortedTable::comparable(StringObject::class, 'int');

        self::assertSame(StringObject::class, $table->keyType());
        self::assertSame('int', $table->valueType());
    }

    public function test_that_callback_factory_creates_a_table_with_custom_key_ordering(): void
    {
        $table = SortedTable::callback(fn(int $a, int $b): int => $a - $b, 'int', 'string');
        $table->set(3, 'three');
        $table->set(1, 'one');

        $keys = [];
        foreach ($table->keys() as $key) {
            $keys[] = $key;
        }

        self::assertSame([1, 3], $keys);
    }

    public function test_that_create_factory_creates_a_table_with_the_given_comparator(): void
    {
        $table = SortedTable::create(new IntegerComparator(), 'int', 'string');

        self::assertSame('int', $table->keyType());
        self::assertSame('string', $table->valueType());
    }

    public function test_that_create_factory_with_no_types_creates_a_dynamic_table(): void
    {
        $table = SortedTable::create(new IntegerComparator());

        self::assertNull($table->keyType());
        self::assertNull($table->valueType());
    }

    public function test_that_set_and_get_store_and_retrieve_values_in_key_order(): void
    {
        $table = SortedTable::integer('string');
        $table->set(2, 'two');
        $table->set(1, 'one');

        self::assertSame('one', $table->get(1));
        self::assertSame('two', $table->get(2));
    }

    public function test_that_set_overwrites_an_existing_key_and_count_does_not_increase(): void
    {
        $table = SortedTable::integer('string');
        $table->set(1, 'one');
        $table->set(1, 'ONE');

        self::assertSame('ONE', $table->get(1));
        self::assertSame(1, $table->count());
    }

    public function test_that_set_throws_for_a_key_of_the_wrong_type(): void
    {
        $table = SortedTable::integer('string');

        $this->expectException(AssertionError::class);
        $table->set('not-an-int', 'value');
    }

    public function test_that_set_throws_for_a_value_of_the_wrong_type(): void
    {
        $table = SortedTable::integer('string');

        $this->expectException(AssertionError::class);
        $table->set(1, 42);
    }

    public function test_that_get_throws_key_exception_for_a_missing_key(): void
    {
        $table = SortedTable::integer('string');

        $this->expectException(KeyException::class);
        $table->get(999);
    }

    public function test_that_has_returns_true_for_existing_key_and_false_for_missing(): void
    {
        $table = SortedTable::integer('string');
        $table->set(1, 'one');

        self::assertTrue($table->has(1));
        self::assertFalse($table->has(99));
    }

    public function test_that_remove_deletes_an_entry(): void
    {
        $table = SortedTable::integer('string');
        $table->set(1, 'one');
        $table->remove(1);

        self::assertFalse($table->has(1));
        self::assertSame(0, $table->count());
    }

    public function test_that_remove_min_removes_the_entry_with_the_smallest_key(): void
    {
        $table = SortedTable::integer('string');
        $table->set(3, 'three');
        $table->set(1, 'one');
        $table->set(2, 'two');
        $table->removeMin();

        self::assertFalse($table->has(1));
        self::assertSame(2, $table->count());
    }

    public function test_that_remove_min_with_callback_removes_the_entry_with_the_smallest_field_value(): void
    {
        $table = SortedTable::string('string');
        $table->set('a', 'hi');
        $table->set('b', 'hello');
        $table->set('c', 'hey');
        $table->removeMin(fn(string $v): int => strlen($v));

        self::assertFalse($table->has('a'));
    }

    public function test_that_remove_min_throws_for_an_empty_table(): void
    {
        $table = SortedTable::integer('string');

        $this->expectException(UnderflowException::class);
        $table->removeMin();
    }

    public function test_that_remove_max_removes_the_entry_with_the_largest_key(): void
    {
        $table = SortedTable::integer('string');
        $table->set(3, 'three');
        $table->set(1, 'one');
        $table->set(2, 'two');
        $table->removeMax();

        self::assertFalse($table->has(3));
        self::assertSame(2, $table->count());
    }

    public function test_that_remove_max_with_callback_removes_the_entry_with_the_largest_field_value(): void
    {
        $table = SortedTable::string('string');
        $table->set('a', 'hi');
        $table->set('b', 'hello');
        $table->set('c', 'hey');
        $table->removeMax(fn(string $v): int => strlen($v));

        self::assertFalse($table->has('b'));
    }

    public function test_that_remove_max_throws_for_an_empty_table(): void
    {
        $table = SortedTable::integer('string');

        $this->expectException(UnderflowException::class);
        $table->removeMax();
    }

    public function test_that_min_returns_the_smallest_key(): void
    {
        $table = SortedTable::integer('string');
        $table->set(3, 'three');
        $table->set(1, 'one');
        $table->set(2, 'two');

        self::assertSame(1, $table->min());
    }

    public function test_that_min_with_callback_returns_the_key_of_the_entry_with_the_smallest_field_value(): void
    {
        $table = SortedTable::string('string');
        $table->set('a', 'hi');
        $table->set('b', 'hello');

        self::assertSame('a', $table->min(fn(string $v): int => strlen($v)));
    }

    public function test_that_min_throws_for_an_empty_table(): void
    {
        $table = SortedTable::integer('string');

        $this->expectException(UnderflowException::class);
        $table->min();
    }

    public function test_that_max_returns_the_largest_key(): void
    {
        $table = SortedTable::integer('string');
        $table->set(3, 'three');
        $table->set(1, 'one');
        $table->set(2, 'two');

        self::assertSame(3, $table->max());
    }

    public function test_that_max_with_callback_returns_the_key_of_the_entry_with_the_largest_field_value(): void
    {
        $table = SortedTable::string('string');
        $table->set('a', 'hi');
        $table->set('b', 'hello');

        self::assertSame('b', $table->max(fn(string $v): int => strlen($v)));
    }

    public function test_that_max_throws_for_an_empty_table(): void
    {
        $table = SortedTable::integer('string');

        $this->expectException(UnderflowException::class);
        $table->max();
    }

    public function test_that_floor_returns_the_largest_key_less_than_or_equal_to_the_given_value(): void
    {
        $table = SortedTable::integer('string');
        $table->set(1, 'one');
        $table->set(3, 'three');
        $table->set(5, 'five');

        self::assertSame(3, $table->floor(4));
    }

    public function test_that_ceiling_returns_the_smallest_key_greater_than_or_equal_to_the_given_value(): void
    {
        $table = SortedTable::integer('string');
        $table->set(1, 'one');
        $table->set(3, 'three');
        $table->set(5, 'five');

        self::assertSame(3, $table->ceiling(2));
    }

    public function test_that_rank_returns_the_number_of_keys_less_than_the_given_key(): void
    {
        $table = SortedTable::integer('string');
        $table->set(10, 'ten');
        $table->set(20, 'twenty');
        $table->set(30, 'thirty');

        self::assertSame(1, $table->rank(20));
    }

    public function test_that_select_returns_the_key_at_the_given_rank(): void
    {
        $table = SortedTable::integer('string');
        $table->set(10, 'ten');
        $table->set(20, 'twenty');
        $table->set(30, 'thirty');

        self::assertSame(20, $table->select(1));
    }

    public function test_that_range_keys_returns_keys_between_two_values_inclusive(): void
    {
        $table = SortedTable::integer('string');
        $table->set(1, 'one');
        $table->set(2, 'two');
        $table->set(3, 'three');
        $table->set(4, 'four');
        $table->set(5, 'five');

        $result = [];
        foreach ($table->rangeKeys(2, 4) as $key) {
            $result[] = $key;
        }

        self::assertSame([2, 3, 4], $result);
    }

    public function test_that_range_count_returns_the_count_of_keys_between_two_values(): void
    {
        $table = SortedTable::integer('string');
        $table->set(1, 'one');
        $table->set(2, 'two');
        $table->set(3, 'three');
        $table->set(4, 'four');
        $table->set(5, 'five');

        self::assertSame(3, $table->rangeCount(2, 4));
    }

    public function test_that_keys_returns_all_keys_in_sorted_order(): void
    {
        $table = SortedTable::integer('string');
        $table->set(3, 'three');
        $table->set(1, 'one');
        $table->set(2, 'two');

        $keys = [];
        foreach ($table->keys() as $key) {
            $keys[] = $key;
        }

        self::assertSame([1, 2, 3], $keys);
    }

    public function test_that_each_iterates_all_entries_in_sorted_key_order(): void
    {
        $table = SortedTable::integer('string');
        $table->set(3, 'three');
        $table->set(1, 'one');
        $table->set(2, 'two');

        $seen = [];
        $table->each(function (string $value, int $key) use (&$seen): void {
            $seen[$key] = $value;
        });

        self::assertSame([1 => 'one', 2 => 'two', 3 => 'three'], $seen);
    }

    public function test_that_map_returns_a_new_table_with_transformed_values(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);

        $mapped = $table->map(fn(int $v): int => $v * 2, 'int');

        self::assertSame(20, $mapped->get(1));
        self::assertSame(40, $mapped->get(2));
    }

    public function test_that_filter_returns_a_new_table_with_only_matching_entries(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);
        $table->set(3, 30);

        $filtered = $table->filter(fn(int $v): bool => $v > 10);

        self::assertFalse($filtered->has(1));
        self::assertTrue($filtered->has(2));
        self::assertTrue($filtered->has(3));
    }

    public function test_that_reject_returns_entries_not_matching_the_predicate(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);
        $table->set(3, 30);

        $rejected = $table->reject(fn(int $v): bool => $v > 10);

        self::assertTrue($rejected->has(1));
        self::assertFalse($rejected->has(2));
        self::assertFalse($rejected->has(3));
    }

    public function test_that_reduce_accumulates_to_a_single_value(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);
        $table->set(3, 30);

        $result = $table->reduce(fn(int $acc, int $v): int => $acc + $v, 0);

        self::assertSame(60, $result);
    }

    public function test_that_sum_returns_correct_value(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);

        self::assertSame(30, $table->sum());
    }

    public function test_that_sum_returns_null_for_an_empty_table(): void
    {
        self::assertNull(SortedTable::integer('int')->sum());
    }

    public function test_that_average_returns_correct_value(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);

        self::assertSame(15, $table->average());
    }

    public function test_that_average_returns_null_for_an_empty_table(): void
    {
        self::assertNull(SortedTable::integer('int')->average());
    }

    public function test_that_find_returns_the_key_of_the_first_matching_entry(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);
        $table->set(3, 30);

        self::assertSame(2, $table->find(fn(int $v): bool => $v === 20));
    }

    public function test_that_find_returns_null_when_no_entry_matches(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);

        self::assertNull($table->find(fn(int $v): bool => $v === 99));
    }

    public function test_that_any_returns_true_when_at_least_one_entry_matches_and_false_otherwise(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);

        self::assertTrue($table->any(fn(int $v): bool => $v > 10));
        self::assertFalse($table->any(fn(int $v): bool => $v > 100));
    }

    public function test_that_every_returns_true_when_all_entries_match_and_false_otherwise(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);

        self::assertTrue($table->every(fn(int $v): bool => $v > 0));
        self::assertFalse($table->every(fn(int $v): bool => $v > 10));
    }

    public function test_that_partition_splits_entries_into_matching_and_non_matching(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);
        $table->set(3, 30);

        [$pass, $fail] = $table->partition(fn(int $v): bool => $v > 10);

        self::assertTrue($pass->has(2));
        self::assertTrue($pass->has(3));
        self::assertFalse($pass->has(1));
        self::assertTrue($fail->has(1));
    }

    public function test_that_is_empty_returns_true_for_empty_and_false_when_entries_exist(): void
    {
        $table = SortedTable::integer('int');

        self::assertTrue($table->isEmpty());

        $table->set(1, 10);

        self::assertFalse($table->isEmpty());
    }

    public function test_that_count_returns_the_correct_count(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        $table->set(2, 20);

        self::assertSame(2, $table->count());
    }

    public function test_that_clone_produces_an_independent_copy(): void
    {
        $original = SortedTable::integer('int');
        $original->set(1, 10);

        $clone = clone $original;
        $clone->set(2, 20);

        self::assertTrue($original->has(1));
        self::assertFalse($original->has(2));
        self::assertTrue($clone->has(1));
        self::assertTrue($clone->has(2));
    }

    public function test_that_foreach_visits_all_entries_in_sorted_key_order(): void
    {
        $table = SortedTable::integer('int');
        $table->set(3, 30);
        $table->set(1, 10);
        $table->set(2, 20);

        $seen = [];
        foreach ($table as $key => $value) {
            $seen[$key] = $value;
        }

        self::assertSame([1 => 10, 2 => 20, 3 => 30], $seen);
    }

    public function test_that_offset_set_adds_an_entry(): void
    {
        $table = SortedTable::integer('int');
        $table[1] = 100;

        self::assertSame(100, $table->get(1));
    }

    public function test_that_offset_get_returns_the_value_at_the_given_key(): void
    {
        $table = SortedTable::integer('int');
        $table->set(5, 50);

        self::assertSame(50, $table[5]);
    }

    public function test_that_offset_exists_returns_true_for_existing_key_and_false_for_missing(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);

        self::assertTrue(isset($table[1]));
        self::assertFalse(isset($table[99]));
    }

    public function test_that_offset_unset_removes_the_entry_at_the_given_key(): void
    {
        $table = SortedTable::integer('int');
        $table->set(1, 10);
        unset($table[1]);

        self::assertFalse($table->has(1));
    }
}
