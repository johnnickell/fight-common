<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Chain;

use Fight\Common\Domain\Collection\Chain\KeyValueBucket;
use Fight\Common\Domain\Collection\Chain\TerminalBucket;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KeyValueBucket::class)]
class KeyValueBucketTest extends UnitTestCase
{
    public function test_that_key_returns_the_constructed_key(): void
    {
        $bucket = new KeyValueBucket('myKey', 'myValue');

        self::assertSame('myKey', $bucket->key());
    }

    public function test_that_value_returns_the_constructed_value(): void
    {
        $bucket = new KeyValueBucket('myKey', 'myValue');

        self::assertSame('myValue', $bucket->value());
    }

    public function test_that_next_returns_null_by_default(): void
    {
        $bucket = new KeyValueBucket('k', 'v');

        self::assertNull($bucket->next());
    }

    public function test_that_prev_returns_null_by_default(): void
    {
        $bucket = new KeyValueBucket('k', 'v');

        self::assertNull($bucket->prev());
    }

    public function test_that_set_next_links_to_the_given_bucket(): void
    {
        $bucket = new KeyValueBucket('k', 'v');
        $next = new TerminalBucket();

        $bucket->setNext($next);

        self::assertSame($next, $bucket->next());
    }

    public function test_that_set_prev_links_to_the_given_bucket(): void
    {
        $bucket = new KeyValueBucket('k', 'v');
        $prev = new TerminalBucket();

        $bucket->setPrev($prev);

        self::assertSame($prev, $bucket->prev());
    }

    public function test_that_set_next_accepts_null_to_unlink(): void
    {
        $bucket = new KeyValueBucket('k', 'v');
        $bucket->setNext(new TerminalBucket());

        $bucket->setNext(null);

        self::assertNull($bucket->next());
    }

    public function test_that_set_prev_accepts_null_to_unlink(): void
    {
        $bucket = new KeyValueBucket('k', 'v');
        $bucket->setPrev(new TerminalBucket());

        $bucket->setPrev(null);

        self::assertNull($bucket->prev());
    }
}
