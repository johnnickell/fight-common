<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection;

use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HashTable::class)]
class HashTableTest extends UnitTestCase
{
    public function test_that_of_creates_a_typed_collection_and_key_and_value_type_return_correct_types(): void
    {
        $table = HashTable::of('string', 'int');

        self::assertSame('string', $table->keyType());
        self::assertSame('int', $table->valueType());
    }

    public function test_that_of_with_no_arguments_creates_a_dynamic_collection(): void
    {
        $table = HashTable::of();

        self::assertNull($table->keyType());
        self::assertNull($table->valueType());
    }

    public function test_that_set_adds_a_key_value_pair_and_get_returns_the_correct_value(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('answer', 42);

        self::assertSame(42, $table->get('answer'));
    }

    public function test_that_set_overwrites_an_existing_key_and_count_does_not_increase(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('a', 2);

        self::assertSame(2, $table->get('a'));
        self::assertSame(1, $table->count());
    }

    public function test_that_get_throws_key_exception_for_a_missing_key(): void
    {
        $table = HashTable::of('string', 'int');

        $this->expectException(KeyException::class);
        $table->get('missing');
    }

    public function test_that_has_returns_true_for_existing_key_and_false_for_missing(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);

        self::assertTrue($table->has('a'));
        self::assertFalse($table->has('b'));
    }

    public function test_that_remove_deletes_an_entry(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->remove('a');

        self::assertFalse($table->has('a'));
        self::assertSame(0, $table->count());
    }

    public function test_that_remove_of_absent_key_is_a_no_op(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->remove('z');

        self::assertSame(1, $table->count());
    }

    public function test_that_keys_returns_all_keys(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('x', 1);
        $table->set('y', 2);

        $keys = [];
        foreach ($table->keys() as $key) {
            $keys[] = $key;
        }
        sort($keys);

        self::assertSame(['x', 'y'], $keys);
    }

    public function test_that_max_without_callback_returns_the_key_of_the_entry_with_the_greatest_value(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 30);
        $table->set('b', 10);
        $table->set('c', 20);

        self::assertSame('a', $table->max());
    }

    public function test_that_max_with_callback_returns_the_key_of_the_entry_with_the_greatest_field_value(): void
    {
        $table = HashTable::of('string', 'string');
        $table->set('short', 'hi');
        $table->set('long', 'hello world');

        self::assertSame('long', $table->max(fn(string $v): int => strlen($v)));
    }

    public function test_that_max_returns_null_for_empty_collection(): void
    {
        self::assertNull(HashTable::of('string', 'int')->max());
    }

    public function test_that_min_without_callback_returns_the_key_of_the_entry_with_the_smallest_value(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 30);
        $table->set('b', 10);
        $table->set('c', 20);

        self::assertSame('b', $table->min());
    }

    public function test_that_min_with_callback_returns_the_key_of_the_entry_with_the_smallest_field_value(): void
    {
        $table = HashTable::of('string', 'string');
        $table->set('short', 'hi');
        $table->set('long', 'hello world');

        self::assertSame('short', $table->min(fn(string $v): int => strlen($v)));
    }

    public function test_that_min_returns_null_for_empty_collection(): void
    {
        self::assertNull(HashTable::of('string', 'int')->min());
    }

    public function test_that_sum_returns_correct_value(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 10);
        $table->set('b', 20);

        self::assertSame(30, $table->sum());
    }

    public function test_that_sum_returns_null_for_empty_collection(): void
    {
        self::assertNull(HashTable::of('string', 'int')->sum());
    }

    public function test_that_average_returns_correct_value(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 10);
        $table->set('b', 20);

        self::assertSame(15, $table->average());
    }

    public function test_that_average_returns_null_for_empty_collection(): void
    {
        self::assertNull(HashTable::of('string', 'int')->average());
    }

    public function test_that_each_iterates_all_values(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('b', 2);

        $seen = [];
        $table->each(function (int $value, string $key) use (&$seen): void {
            $seen[$key] = $value;
        });
        ksort($seen);

        self::assertSame(['a' => 1, 'b' => 2], $seen);
    }

    public function test_that_map_returns_a_new_table_with_transformed_values(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('b', 2);

        $mapped = $table->map(fn(int $v): int => $v * 10, 'int');

        self::assertSame(10, $mapped->get('a'));
        self::assertSame(20, $mapped->get('b'));
    }

    public function test_that_filter_returns_a_new_table_with_only_matching_entries(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('b', 2);
        $table->set('c', 3);

        $filtered = $table->filter(fn(int $v): bool => $v > 1);

        self::assertFalse($filtered->has('a'));
        self::assertTrue($filtered->has('b'));
        self::assertTrue($filtered->has('c'));
    }

    public function test_that_reduce_accumulates_to_a_single_value(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('b', 2);
        $table->set('c', 3);

        $result = $table->reduce(fn(int $acc, int $v): int => $acc + $v, 0);

        self::assertSame(6, $result);
    }

    public function test_that_find_returns_the_key_of_the_first_matching_entry(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 10);
        $table->set('b', 20);

        self::assertSame('b', $table->find(fn(int $v): bool => $v === 20));
    }

    public function test_that_find_returns_null_when_no_entry_matches(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);

        self::assertNull($table->find(fn(int $v): bool => $v === 99));
    }

    public function test_that_reject_returns_entries_not_matching_the_predicate(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('b', 2);
        $table->set('c', 3);

        $rejected = $table->reject(fn(int $v): bool => $v > 1);

        self::assertTrue($rejected->has('a'));
        self::assertFalse($rejected->has('b'));
        self::assertFalse($rejected->has('c'));
    }

    public function test_that_any_returns_true_when_at_least_one_entry_matches_and_false_otherwise(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('b', 2);

        self::assertTrue($table->any(fn(int $v): bool => $v > 1));
        self::assertFalse($table->any(fn(int $v): bool => $v > 10));
    }

    public function test_that_every_returns_true_when_all_entries_match_and_false_otherwise(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('b', 2);

        self::assertTrue($table->every(fn(int $v): bool => $v > 0));
        self::assertFalse($table->every(fn(int $v): bool => $v > 1));
    }

    public function test_that_partition_splits_entries_into_matching_and_non_matching(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('b', 2);
        $table->set('c', 3);

        [$pass, $fail] = $table->partition(fn(int $v): bool => $v % 2 !== 0);

        self::assertTrue($pass->has('a'));
        self::assertTrue($pass->has('c'));
        self::assertFalse($pass->has('b'));
        self::assertTrue($fail->has('b'));
    }

    public function test_that_is_empty_returns_true_for_empty_and_false_when_entries_exist(): void
    {
        $table = HashTable::of('string', 'int');

        self::assertTrue($table->isEmpty());

        $table->set('a', 1);

        self::assertFalse($table->isEmpty());
    }

    public function test_that_count_returns_the_correct_count(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('b', 2);

        self::assertSame(2, $table->count());
    }

    public function test_that_clone_produces_an_independent_copy(): void
    {
        $original = HashTable::of('string', 'int');
        $original->set('a', 1);

        $clone = clone $original;
        $clone->set('b', 2);

        self::assertTrue($original->has('a'));
        self::assertFalse($original->has('b'));
        self::assertTrue($clone->has('a'));
        self::assertTrue($clone->has('b'));
    }

    public function test_that_foreach_iteration_visits_all_key_value_pairs(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        $table->set('b', 2);

        $seen = [];
        foreach ($table as $key => $value) {
            $seen[$key] = $value;
        }
        ksort($seen);

        self::assertSame(['a' => 1, 'b' => 2], $seen);
    }

    public function test_that_offset_set_adds_an_entry(): void
    {
        $table = HashTable::of('string', 'int');
        $table['answer'] = 42;

        self::assertSame(42, $table->get('answer'));
    }

    public function test_that_offset_get_returns_the_value_at_the_given_key(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('x', 7);

        self::assertSame(7, $table['x']);
    }

    public function test_that_offset_exists_returns_true_for_existing_key_and_false_for_missing(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);

        self::assertTrue(isset($table['a']));
        self::assertFalse(isset($table['z']));
    }

    public function test_that_offset_unset_removes_the_entry_at_the_given_key(): void
    {
        $table = HashTable::of('string', 'int');
        $table->set('a', 1);
        unset($table['a']);

        self::assertFalse($table->has('a'));
    }
}
