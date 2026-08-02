<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\EventSourcing;

use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(StreamId::class)]
class StreamIdTest extends UnitTestCase
{
    public function test_that_it_exposes_stable_aggregate_identity_without_a_php_aggregate_class(): void
    {
        $stream = new StreamId('orders', 'order-42');

        self::assertSame('orders', $stream->aggregateName());
        self::assertSame('order-42', $stream->identifier());
    }

    #[DataProvider('invalid_identity_provider')]
    public function test_that_it_rejects_an_empty_aggregate_name_or_identifier(
        string $aggregateName,
        string $identifier,
    ): void {
        $this->expectException(DomainException::class);

        new StreamId($aggregateName, $identifier);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid_identity_provider(): iterable
    {
        yield 'empty aggregate name' => ['', 'order-42'];
        yield 'empty identifier' => ['orders', ''];
    }
}
