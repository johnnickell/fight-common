<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Chain;

use Fight\Common\Domain\Collection\Chain\ItemBucket;
use Fight\Common\Domain\Collection\Chain\TerminalBucket;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TerminalBucket::class)]
class TerminalBucketTest extends UnitTestCase
{
    public function test_that_next_returns_null_by_default(): void
    {
        $terminal = new TerminalBucket();

        self::assertNull($terminal->next());
    }

    public function test_that_prev_returns_null_by_default(): void
    {
        $terminal = new TerminalBucket();

        self::assertNull($terminal->prev());
    }

    public function test_that_set_next_links_to_the_given_bucket(): void
    {
        $terminal = new TerminalBucket();
        $node = new ItemBucket('x');

        $terminal->setNext($node);

        self::assertSame($node, $terminal->next());
    }

    public function test_that_set_prev_links_to_the_given_bucket(): void
    {
        $terminal = new TerminalBucket();
        $node = new ItemBucket('x');

        $terminal->setPrev($node);

        self::assertSame($node, $terminal->prev());
    }

    public function test_that_set_next_accepts_null_to_unlink(): void
    {
        $terminal = new TerminalBucket();
        $terminal->setNext(new ItemBucket('x'));

        $terminal->setNext(null);

        self::assertNull($terminal->next());
    }

    public function test_that_set_prev_accepts_null_to_unlink(): void
    {
        $terminal = new TerminalBucket();
        $terminal->setPrev(new ItemBucket('x'));

        $terminal->setPrev(null);

        self::assertNull($terminal->prev());
    }

    public function test_that_terminal_buckets_act_as_sentinels_in_a_doubly_linked_list(): void
    {
        $head = new TerminalBucket();
        $tail = new TerminalBucket();
        $node = new ItemBucket('payload');

        $head->setNext($node);
        $node->setPrev($head);
        $node->setNext($tail);
        $tail->setPrev($node);

        // Forward traversal stops at tail sentinel
        $current = $head->next();
        self::assertInstanceOf(ItemBucket::class, $current);
        self::assertSame('payload', $current->item());

        $current = $current->next();
        self::assertInstanceOf(TerminalBucket::class, $current);

        // Backward traversal stops at head sentinel
        $current = $tail->prev();
        self::assertInstanceOf(ItemBucket::class, $current);

        $current = $current->prev();
        self::assertInstanceOf(TerminalBucket::class, $current);
    }
}
