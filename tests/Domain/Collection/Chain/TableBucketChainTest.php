<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Chain;

use Fight\Common\Domain\Collection\Chain\TableBucketChain;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TableBucketChain::class)]
class TableBucketChainTest extends UnitTestCase
{
    public function test_that_set_returns_true_and_entry_is_present_when_adding_new_key(): void
    {
        $chain = new TableBucketChain();

        $result = $chain->set('foo', 'bar');

        self::assertTrue($result);
        self::assertTrue($chain->has('foo'));
        self::assertSame(1, $chain->count());
        self::assertFalse($chain->isEmpty());
    }

    public function test_that_set_returns_false_and_updates_value_when_replacing_existing_key(): void
    {
        $chain = new TableBucketChain();
        $chain->set('foo', 'old');

        $result = $chain->set('foo', 'new');

        self::assertFalse($result);
        self::assertSame('new', $chain->get('foo'));
        self::assertSame(1, $chain->count());
    }

    public function test_that_get_returns_correct_value_for_existing_key(): void
    {
        $chain = new TableBucketChain();
        $chain->set('key', 'value');

        self::assertSame('value', $chain->get('key'));
    }

    public function test_that_get_throws_for_missing_key(): void
    {
        $chain = new TableBucketChain();

        $this->expectException(KeyException::class);
        $chain->get('missing');
    }

    public function test_that_has_returns_true_for_existing_key(): void
    {
        $chain = new TableBucketChain();
        $chain->set('k', 'v');

        self::assertTrue($chain->has('k'));
    }

    public function test_that_has_returns_false_for_missing_key(): void
    {
        $chain = new TableBucketChain();

        self::assertFalse($chain->has('missing'));
    }

    public function test_that_remove_deletes_entry_and_returns_true(): void
    {
        $chain = new TableBucketChain();
        $chain->set('k', 'v');

        $result = $chain->remove('k');

        self::assertTrue($result);
        self::assertFalse($chain->has('k'));
        self::assertSame(0, $chain->count());
        self::assertTrue($chain->isEmpty());
    }

    public function test_that_remove_returns_false_for_missing_key(): void
    {
        $chain = new TableBucketChain();

        $result = $chain->remove('missing');

        self::assertFalse($result);
    }

    public function test_that_clone_produces_independent_copy(): void
    {
        $original = new TableBucketChain();
        $original->set('k', 'original');

        $clone = clone $original;
        $clone->set('k', 'changed');

        self::assertSame('original', $original->get('k'));
        self::assertSame('changed', $clone->get('k'));
    }

    public function test_that_forward_navigation_visits_all_entries_in_order(): void
    {
        $chain = new TableBucketChain();
        $chain->set('a', 1);
        $chain->set('b', 2);
        // set() inserts at front: chain is [b, a] from head to tail

        $chain->rewind();

        self::assertTrue($chain->valid());
        self::assertSame('b', $chain->key());
        self::assertSame(2, $chain->current());

        $chain->next();

        self::assertTrue($chain->valid());
        self::assertSame('a', $chain->key());
        self::assertSame(1, $chain->current());

        $chain->next();

        self::assertFalse($chain->valid());
        self::assertNull($chain->key());
        self::assertNull($chain->current());
    }

    public function test_that_next_is_no_op_when_past_end(): void
    {
        $chain = new TableBucketChain();
        $chain->set('a', 1);
        $chain->rewind();
        $chain->next(); // move to tail (TerminalBucket)

        $chain->next(); // no-op

        self::assertFalse($chain->valid());
    }

    public function test_that_end_and_backward_navigation_visits_all_entries_in_reverse(): void
    {
        $chain = new TableBucketChain();
        $chain->set('a', 1);
        $chain->set('b', 2);
        // chain from head to tail: [b, a]

        $chain->end();

        self::assertTrue($chain->valid());
        self::assertSame('a', $chain->key());
        self::assertSame(1, $chain->current());

        $chain->prev();

        self::assertTrue($chain->valid());
        self::assertSame('b', $chain->key());
        self::assertSame(2, $chain->current());

        $chain->prev();

        self::assertFalse($chain->valid());
        self::assertNull($chain->key());
        self::assertNull($chain->current());
    }

    public function test_that_prev_is_no_op_when_before_start(): void
    {
        $chain = new TableBucketChain();
        $chain->set('a', 1);
        $chain->rewind();
        $chain->prev(); // move to head (TerminalBucket)

        $chain->prev(); // no-op

        self::assertFalse($chain->valid());
    }
}
