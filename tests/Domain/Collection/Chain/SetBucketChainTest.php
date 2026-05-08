<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Chain;

use Fight\Common\Domain\Collection\Chain\SetBucketChain;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SetBucketChain::class)]
class SetBucketChainTest extends UnitTestCase
{
    public function test_that_add_returns_true_and_item_is_present_when_adding_new_value(): void
    {
        $chain = new SetBucketChain();

        $result = $chain->add('foo');

        self::assertTrue($result);
        self::assertTrue($chain->contains('foo'));
        self::assertSame(1, $chain->count());
        self::assertFalse($chain->isEmpty());
    }

    public function test_that_add_returns_false_and_replaces_existing_value(): void
    {
        $chain = new SetBucketChain();
        $chain->add('foo');

        $result = $chain->add('foo');

        self::assertFalse($result);
        self::assertTrue($chain->contains('foo'));
        self::assertSame(1, $chain->count());
    }

    public function test_that_contains_returns_true_for_existing_value(): void
    {
        $chain = new SetBucketChain();
        $chain->add('hello');

        self::assertTrue($chain->contains('hello'));
    }

    public function test_that_contains_returns_false_for_missing_value(): void
    {
        $chain = new SetBucketChain();

        self::assertFalse($chain->contains('missing'));
    }

    public function test_that_remove_deletes_item_and_returns_true(): void
    {
        $chain = new SetBucketChain();
        $chain->add('hello');

        $result = $chain->remove('hello');

        self::assertTrue($result);
        self::assertFalse($chain->contains('hello'));
        self::assertSame(0, $chain->count());
        self::assertTrue($chain->isEmpty());
    }

    public function test_that_remove_returns_false_for_missing_value(): void
    {
        $chain = new SetBucketChain();

        $result = $chain->remove('missing');

        self::assertFalse($result);
    }

    public function test_that_clone_produces_independent_copy(): void
    {
        $original = new SetBucketChain();
        $original->add('hello');

        $clone = clone $original;
        $clone->remove('hello');

        self::assertTrue($original->contains('hello'));
        self::assertFalse($clone->contains('hello'));
    }

    public function test_that_forward_navigation_visits_all_items_in_order(): void
    {
        $chain = new SetBucketChain();
        $chain->add('a');
        $chain->add('b');
        // add() inserts at front: chain is [b, a] from head to tail

        $chain->rewind();

        self::assertTrue($chain->valid());
        self::assertSame(0, $chain->key());
        self::assertSame('b', $chain->current());

        $chain->next();

        self::assertTrue($chain->valid());
        self::assertSame(1, $chain->key());
        self::assertSame('a', $chain->current());

        $chain->next();

        self::assertFalse($chain->valid());
        self::assertNull($chain->key());
        self::assertNull($chain->current());
    }

    public function test_that_next_is_no_op_when_past_end(): void
    {
        $chain = new SetBucketChain();
        $chain->add('a');
        $chain->rewind();
        $chain->next(); // move to tail (TerminalBucket)

        $chain->next(); // no-op

        self::assertFalse($chain->valid());
    }

    public function test_that_end_and_backward_navigation_visits_all_items_in_reverse(): void
    {
        $chain = new SetBucketChain();
        $chain->add('a');
        $chain->add('b');
        // chain from head to tail: [b, a]

        $chain->end();

        self::assertTrue($chain->valid());
        self::assertSame(1, $chain->key());
        self::assertSame('a', $chain->current());

        $chain->prev();

        self::assertTrue($chain->valid());
        self::assertSame(0, $chain->key());
        self::assertSame('b', $chain->current());

        $chain->prev();

        self::assertFalse($chain->valid());
        self::assertNull($chain->key());
        self::assertNull($chain->current());
    }

    public function test_that_prev_is_no_op_when_before_start(): void
    {
        $chain = new SetBucketChain();
        $chain->add('a');
        $chain->rewind();
        $chain->prev(); // move to head (TerminalBucket)

        $chain->prev(); // no-op

        self::assertFalse($chain->valid());
    }
}
