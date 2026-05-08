<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Tree;

use Fight\Common\Domain\Collection\Comparison\IntegerComparator;
use Fight\Common\Domain\Collection\Comparison\StringComparator;
use Fight\Common\Domain\Collection\Tree\RedBlackSearchTree;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Exception\UnderflowException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RedBlackSearchTree::class)]
class RedBlackSearchTreeTest extends UnitTestCase
{
    private function intTree(): RedBlackSearchTree
    {
        return new RedBlackSearchTree(new IntegerComparator());
    }

    private function stringTree(): RedBlackSearchTree
    {
        return new RedBlackSearchTree(new StringComparator());
    }

    public function test_that_is_empty_returns_true_for_an_empty_tree(): void
    {
        self::assertTrue($this->intTree()->isEmpty());
    }

    public function test_that_is_empty_returns_false_when_entries_exist(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');

        self::assertFalse($tree->isEmpty());
    }

    public function test_that_count_returns_zero_for_an_empty_tree(): void
    {
        self::assertSame(0, $this->intTree()->count());
    }

    public function test_that_set_and_get_store_and_retrieve_a_value(): void
    {
        $tree = $this->intTree();
        $tree->set(42, 'answer');

        self::assertSame('answer', $tree->get(42));
    }

    public function test_that_set_overwrites_an_existing_key(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'first');
        $tree->set(1, 'second');

        self::assertSame('second', $tree->get(1));
        self::assertSame(1, $tree->count());
    }

    public function test_that_get_throws_key_exception_for_a_missing_key(): void
    {
        $tree = $this->intTree();

        $this->expectException(KeyException::class);
        $tree->get(99);
    }

    public function test_that_has_returns_true_for_an_existing_key(): void
    {
        $tree = $this->intTree();
        $tree->set(5, 'five');

        self::assertTrue($tree->has(5));
    }

    public function test_that_has_returns_false_for_a_missing_key(): void
    {
        $tree = $this->intTree();

        self::assertFalse($tree->has(5));
    }

    public function test_that_remove_deletes_an_entry_and_has_returns_false(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');
        $tree->set(2, 'two');
        $tree->remove(1);

        self::assertFalse($tree->has(1));
        self::assertSame(1, $tree->count());
    }

    public function test_that_remove_of_absent_key_is_a_no_op(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');
        $tree->remove(99);

        self::assertSame(1, $tree->count());
    }

    public function test_that_remove_last_entry_leaves_empty_tree(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');
        $tree->remove(1);

        self::assertTrue($tree->isEmpty());
    }

    public function test_that_min_returns_the_smallest_key(): void
    {
        $tree = $this->intTree();
        $tree->set(3, 'three');
        $tree->set(1, 'one');
        $tree->set(2, 'two');

        self::assertSame(1, $tree->min());
    }

    public function test_that_min_throws_for_an_empty_tree(): void
    {
        $this->expectException(UnderflowException::class);
        $this->intTree()->min();
    }

    public function test_that_max_returns_the_largest_key(): void
    {
        $tree = $this->intTree();
        $tree->set(3, 'three');
        $tree->set(1, 'one');
        $tree->set(2, 'two');

        self::assertSame(3, $tree->max());
    }

    public function test_that_max_throws_for_an_empty_tree(): void
    {
        $this->expectException(UnderflowException::class);
        $this->intTree()->max();
    }

    public function test_that_remove_min_removes_the_entry_with_the_smallest_key(): void
    {
        $tree = $this->intTree();
        $tree->set(3, 'three');
        $tree->set(1, 'one');
        $tree->set(2, 'two');
        $tree->removeMin();

        self::assertFalse($tree->has(1));
        self::assertSame(2, $tree->count());
    }

    public function test_that_remove_min_throws_for_an_empty_tree(): void
    {
        $this->expectException(UnderflowException::class);
        $this->intTree()->removeMin();
    }

    public function test_that_remove_max_removes_the_entry_with_the_largest_key(): void
    {
        $tree = $this->intTree();
        $tree->set(3, 'three');
        $tree->set(1, 'one');
        $tree->set(2, 'two');
        $tree->removeMax();

        self::assertFalse($tree->has(3));
        self::assertSame(2, $tree->count());
    }

    public function test_that_remove_max_throws_for_an_empty_tree(): void
    {
        $this->expectException(UnderflowException::class);
        $this->intTree()->removeMax();
    }

    public function test_that_floor_returns_the_largest_key_less_than_or_equal_to_the_given_key(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');
        $tree->set(3, 'three');
        $tree->set(5, 'five');

        self::assertSame(3, $tree->floor(4));
    }

    public function test_that_floor_returns_the_key_when_it_exists_exactly(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');
        $tree->set(3, 'three');

        self::assertSame(3, $tree->floor(3));
    }

    public function test_that_floor_returns_null_when_no_key_is_less_than_or_equal_to_the_given_key(): void
    {
        $tree = $this->intTree();
        $tree->set(5, 'five');

        self::assertNull($tree->floor(2));
    }

    public function test_that_floor_throws_for_an_empty_tree(): void
    {
        $this->expectException(UnderflowException::class);
        $this->intTree()->floor(1);
    }

    public function test_that_ceiling_returns_the_smallest_key_greater_than_or_equal_to_the_given_key(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');
        $tree->set(3, 'three');
        $tree->set(5, 'five');

        self::assertSame(3, $tree->ceiling(2));
    }

    public function test_that_ceiling_returns_the_key_when_it_exists_exactly(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');
        $tree->set(3, 'three');

        self::assertSame(3, $tree->ceiling(3));
    }

    public function test_that_ceiling_returns_null_when_no_key_is_greater_than_or_equal_to_the_given_key(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');

        self::assertNull($tree->ceiling(5));
    }

    public function test_that_ceiling_throws_for_an_empty_tree(): void
    {
        $this->expectException(UnderflowException::class);
        $this->intTree()->ceiling(1);
    }

    public function test_that_rank_returns_the_number_of_keys_less_than_the_given_key(): void
    {
        $tree = $this->intTree();
        $tree->set(10, 'ten');
        $tree->set(20, 'twenty');
        $tree->set(30, 'thirty');

        self::assertSame(0, $tree->rank(10));
        self::assertSame(1, $tree->rank(20));
        self::assertSame(2, $tree->rank(30));
    }

    public function test_that_rank_returns_zero_for_an_empty_tree(): void
    {
        self::assertSame(0, $this->intTree()->rank(42));
    }

    public function test_that_select_returns_the_key_at_the_given_rank(): void
    {
        $tree = $this->intTree();
        $tree->set(10, 'ten');
        $tree->set(20, 'twenty');
        $tree->set(30, 'thirty');

        self::assertSame(10, $tree->select(0));
        self::assertSame(20, $tree->select(1));
        self::assertSame(30, $tree->select(2));
    }

    public function test_that_select_throws_lookup_exception_for_a_negative_rank(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');

        $this->expectException(LookupException::class);
        $tree->select(-1);
    }

    public function test_that_select_throws_lookup_exception_for_a_rank_out_of_bounds(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');

        $this->expectException(LookupException::class);
        $tree->select(1);
    }

    public function test_that_keys_returns_all_keys_in_sorted_order(): void
    {
        $tree = $this->intTree();
        $tree->set(3, 'three');
        $tree->set(1, 'one');
        $tree->set(2, 'two');

        $keys = [];
        foreach ($tree->keys() as $key) {
            $keys[] = $key;
        }

        self::assertSame([1, 2, 3], $keys);
    }

    public function test_that_keys_on_an_empty_tree_returns_an_empty_iterable(): void
    {
        $keys = [];
        foreach ($this->intTree()->keys() as $key) {
            $keys[] = $key;
        }

        self::assertSame([], $keys);
    }

    public function test_that_range_keys_returns_all_keys_between_lo_and_hi_inclusive(): void
    {
        $tree = $this->intTree();
        foreach ([1, 2, 3, 4, 5] as $n) {
            $tree->set($n, (string) $n);
        }

        $result = [];
        foreach ($tree->rangeKeys(2, 4) as $key) {
            $result[] = $key;
        }

        self::assertSame([2, 3, 4], $result);
    }

    public function test_that_range_count_returns_the_correct_count_between_two_keys(): void
    {
        $tree = $this->intTree();
        foreach ([1, 2, 3, 4, 5] as $n) {
            $tree->set($n, (string) $n);
        }

        self::assertSame(3, $tree->rangeCount(2, 4));
    }

    public function test_that_range_count_returns_zero_when_lo_is_greater_than_hi(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');
        $tree->set(2, 'two');

        self::assertSame(0, $tree->rangeCount(5, 1));
    }

    public function test_that_range_count_excludes_hi_when_it_is_not_in_the_tree(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');
        $tree->set(2, 'two');
        $tree->set(3, 'three');

        self::assertSame(2, $tree->rangeCount(1, 2));
        self::assertSame(2, $tree->rangeCount(1, 2));
    }

    public function test_that_count_returns_the_correct_number_of_entries(): void
    {
        $tree = $this->intTree();
        $tree->set(1, 'one');
        $tree->set(2, 'two');
        $tree->set(3, 'three');

        self::assertSame(3, $tree->count());
    }

    public function test_that_clone_produces_an_independent_copy(): void
    {
        $original = $this->intTree();
        $original->set(1, 'one');
        $original->set(2, 'two');

        $clone = clone $original;
        $clone->set(3, 'three');
        $clone->remove(1);

        self::assertTrue($original->has(1));
        self::assertFalse($original->has(3));
        self::assertFalse($clone->has(1));
        self::assertTrue($clone->has(3));
    }

    public function test_that_many_insertions_maintain_correct_sorted_order(): void
    {
        $tree = $this->intTree();
        $input = [5, 3, 8, 1, 4, 7, 9, 2, 6, 10];
        foreach ($input as $n) {
            $tree->set($n, $n * 10);
        }

        $keys = [];
        foreach ($tree->keys() as $key) {
            $keys[] = $key;
        }

        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $keys);
        self::assertSame(10, $tree->count());
        self::assertSame(1, $tree->min());
        self::assertSame(10, $tree->max());
    }

    public function test_that_string_tree_maintains_lexicographic_order(): void
    {
        $tree = $this->stringTree();
        $tree->set('banana', 2);
        $tree->set('apple', 1);
        $tree->set('cherry', 3);

        $keys = [];
        foreach ($tree->keys() as $key) {
            $keys[] = $key;
        }

        self::assertSame(['apple', 'banana', 'cherry'], $keys);
    }

    public function test_that_remove_with_two_children_maintains_tree_integrity(): void
    {
        $tree = $this->intTree();
        foreach ([5, 3, 7, 1, 4, 6, 8] as $n) {
            $tree->set($n, (string) $n);
        }

        $tree->remove(3);

        self::assertFalse($tree->has(3));
        self::assertSame(6, $tree->count());

        $keys = [];
        foreach ($tree->keys() as $key) {
            $keys[] = $key;
        }
        self::assertSame([1, 4, 5, 6, 7, 8], $keys);
    }

    public function test_that_repeated_remove_min_drains_the_tree_in_sorted_order(): void
    {
        $tree = $this->intTree();
        foreach ([4, 2, 6, 1, 3, 5, 7] as $n) {
            $tree->set($n, $n);
        }

        $removed = [];
        while (!$tree->isEmpty()) {
            $removed[] = $tree->min();
            $tree->removeMin();
        }

        self::assertSame([1, 2, 3, 4, 5, 6, 7], $removed);
    }

    public function test_that_repeated_remove_max_drains_the_tree_in_reverse_sorted_order(): void
    {
        $tree = $this->intTree();
        foreach ([4, 2, 6, 1, 3, 5, 7] as $n) {
            $tree->set($n, $n);
        }

        $removed = [];
        while (!$tree->isEmpty()) {
            $removed[] = $tree->max();
            $tree->removeMax();
        }

        self::assertSame([7, 6, 5, 4, 3, 2, 1], $removed);
    }
}
