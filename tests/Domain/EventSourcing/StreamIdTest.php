<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\EventSourcing;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Collection\SortedSet;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Identity\Identifier;
use Fight\Common\Domain\Utility\FastHasher;
use Fight\Common\Domain\Value\Basic\StringObject;
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

    public function test_that_equal_tuples_have_value_identity_and_canonical_json(): void
    {
        $first = new StreamId(aggregateName: 'Order', identifier: '123');
        $second = new StreamId('Order', '123');

        self::assertInstanceOf(Identifier::class, $first);
        self::assertNotSame($first, $second);
        self::assertTrue($first->equals($first));
        self::assertTrue($first->equals($second));
        self::assertTrue($second->equals($first));
        self::assertSame(0, $first->compareTo($second));
        self::assertSame($first->hashValue(), $second->hashValue());
        self::assertSame(FastHasher::hash($first), FastHasher::hash($second));
        self::assertSame('stream:v1:T3JkZXI=:MTIz', $first->toString());
        self::assertSame('stream:v1:T3JkZXI=:MTIz', (string) $first);
        self::assertSame('"stream:v1:T3JkZXI=:MTIz"', json_encode($first, JSON_THROW_ON_ERROR));
    }

    #[DataProvider('component_provider')]
    public function test_that_canonical_round_trips_preserve_every_component_byte(
        string $name,
        string $id,
        string $canonical,
    ): void {
        $stream = new StreamId($name, $id);
        $restored = StreamId::fromString($canonical);

        self::assertSame($canonical, $stream->toString());
        self::assertSame($name, $restored->aggregateName());
        self::assertSame($id, $restored->identifier());
        self::assertSame($canonical, $restored->toString());
        self::assertTrue($stream->equals($restored));
        self::assertSame(0, $stream->compareTo($restored));
        self::assertTrue($stream->equals(StreamId::fromString(json_decode(
            json_encode($stream, JSON_THROW_ON_ERROR),
            flags: JSON_THROW_ON_ERROR,
        ))));
    }

    public function test_that_ordering_is_bytewise_antisymmetric_transitive_and_agrees_with_equality(): void
    {
        // Independently ordered raw bytes, deliberately not numeric or Base64 lexical order.
        $ordered = [
            new StreamId("\0", 'x'),
            new StreamId('01', 'x'),
            new StreamId('1', 'x'),
            new StreamId('10', 'x'),
            new StreamId('2', "\0"),
            new StreamId('2', '01'),
            new StreamId('2', '1'),
            new StreamId('2', '10'),
            new StreamId('2', '2'),
            new StreamId('2', "\xff"),
            new StreamId('A', 'x'),
            new StreamId('a', 'x'),
            new StreamId('a:', 'x'),
            new StreamId("\xff", 'x'),
        ];

        foreach ($ordered as $i => $first) {
            foreach ($ordered as $j => $second) {
                self::assertSame($i <=> $j, $first->compareTo(other: $second));
                self::assertSame($j <=> $i, $second->compareTo(other: $first));
                self::assertSame($i === $j, $first->equals($second));
            }
        }
    }

    public function test_that_separator_placement_cannot_collapse_distinct_tuples(): void
    {
        $first = new StreamId('a:b', 'c');
        $second = new StreamId('a', 'b:c');

        self::assertFalse($first->equals($second));
        self::assertNotSame($first->toString(), $second->toString());
        self::assertNotSame($first->hashValue(), $second->hashValue());
    }

    #[DataProvider('wrong_type_provider')]
    public function test_that_wrong_type_equality_is_false_and_comparison_raises_a_domain_failure(mixed $other): void
    {
        $stream = new StreamId('Order', '123');

        self::assertFalse($stream->equals($other));
        $this->expectException(DomainException::class);
        $stream->compareTo($other);
    }

    public function test_that_hash_sets_deduplicate_find_and_remove_equivalent_tuples(): void
    {
        $set = HashSet::of(Identifier::class);
        $set->add(new StreamId('Order', '123'));
        $set->add(new StreamId('Order', '123'));
        $set->add(new StreamId('Invoice', '123'));
        $set->add(new StreamId('Order', '124'));

        self::assertCount(3, $set);
        self::assertTrue($set->contains(StreamId::fromString('stream:v1:T3JkZXI=:MTIz')));
        $set->remove(new StreamId('Order', '123'));
        self::assertCount(2, $set);
        self::assertFalse($set->contains(new StreamId('Order', '123')));
        self::assertTrue($set->contains(new StreamId('Invoice', '123')));
        self::assertTrue($set->contains(new StreamId('Order', '124')));
    }

    public function test_that_hash_tables_replace_values_for_equivalent_keys(): void
    {
        $table = HashTable::of(Identifier::class, 'string');
        $table->set(new StreamId('Order', '123'), 'original');
        $table->set(new StreamId('Order', '123'), 'replacement');
        $table->set(new StreamId('Invoice', '123'), 'other');

        self::assertCount(2, $table);
        self::assertTrue($table->has(new StreamId('Order', '123')));
        self::assertSame('replacement', $table->get(StreamId::fromString('stream:v1:T3JkZXI=:MTIz')));
        self::assertSame('other', $table->get(new StreamId('Invoice', '123')));
    }

    public function test_that_hash_collisions_do_not_merge_different_stream_identities(): void
    {
        // Two unequal tuples with the same default FNV-1a-32 digest, not a mocked hasher.
        $first = new StreamId('orders', '16499');
        $second = new StreamId('orders', '119114');
        self::assertSame(FastHasher::hash($first), FastHasher::hash($second));
        self::assertFalse($first->equals($second));

        $set = HashSet::of(StreamId::class);
        $set->add($first);
        $set->add($second);
        self::assertCount(2, $set);
        $set->remove(new StreamId('orders', '16499'));
        self::assertFalse($set->contains($first));
        self::assertTrue($set->contains(new StreamId('orders', '119114')));

        $table = HashTable::of(StreamId::class, 'string');
        $table->set($first, 'first');
        $table->set($second, 'second');
        $table->set(new StreamId('orders', '16499'), 'updated');
        self::assertCount(2, $table);
        self::assertSame('updated', $table->get($first));
        self::assertSame('second', $table->get(new StreamId('orders', '119114')));
    }

    public function test_that_identifier_lists_preserve_instances_and_comparable_sets_use_tuple_order(): void
    {
        $first = new StreamId('Order', '10');
        $equal = new StreamId('Order', '10');
        $second = new StreamId('Order', '2');
        $third = new StreamId('invoice', '1');
        $list = ArrayList::of(Identifier::class);
        $list->add($first);
        $list->add($equal);
        self::assertSame([$first, $equal], [...$list]);

        $set = SortedSet::comparable(Identifier::class);
        foreach ([$third, $second, $first, $equal] as $stream) {
            $set->add($stream);
        }

        self::assertCount(3, $set);
        self::assertSame(['10', '2', '1'], array_map(
            static fn (StreamId $stream): string => $stream->identifier(),
            [...$set],
        ));
        self::assertTrue($set->contains(new StreamId('Order', '10')));
        $set->remove(new StreamId('Order', '10'));
        self::assertCount(2, $set);
    }

    public function test_that_explicit_custom_comparators_still_own_sorted_set_equivalence(): void
    {
        $set = SortedSet::callback(
            static fn (StreamId $a, StreamId $b): int => strcmp($b->aggregateName(), $a->aggregateName()),
            StreamId::class,
        );
        $set->add(new StreamId('Order', '1'));
        $set->add(new StreamId('Order', '2'));
        $set->add(new StreamId('Invoice', '3'));

        self::assertCount(2, $set);
        self::assertSame(['Order', 'Invoice'], array_map(
            static fn (StreamId $stream): string => $stream->aggregateName(),
            [...$set],
        ));
        self::assertTrue($set->contains(new StreamId('Order', 'not-inserted')));
    }

    public function test_that_pre_value_php_serialized_data_remains_readable(): void
    {
        // Captured from develop 205be9e before Identifier promotion; not generated by the new writer.
        $legacy = 'Tzo0MjoiRmlnaHRcQ29tbW9uXERvbWFpblxFdmVudFNvdXJjaW5nXFN0cmVhbUlkIjoyOntzOjU3OiIA'
            .'RmlnaHRcQ29tbW9uXERvbWFpblxFdmVudFNvdXJjaW5nXFN0cmVhbUlkAGFnZ3JlZ2F0ZU5hbWUiO3M6'
            .'NToiT3JkZXIiO3M6NTQ6IgBGaWdodFxDb21tb25cRG9tYWluXEV2ZW50U291cmNpbmdcU3RyZWFtSWQA'
            .'aWRlbnRpZmllciI7czozOiIxMjMiO30=';
        $restored = unserialize(base64_decode($legacy, true), ['allowed_classes' => [StreamId::class]]);

        self::assertInstanceOf(StreamId::class, $restored);
        self::assertSame('Order', $restored->aggregateName());
        self::assertSame('123', $restored->identifier());
        self::assertTrue($restored->equals(new StreamId('Order', '123')));
        self::assertTrue($restored->equals(unserialize(serialize($restored))));
    }

    #[DataProvider('invalid_frame_provider')]
    public function test_that_reconstruction_rejects_noncanonical_or_invalid_frames(string $frame): void
    {
        $this->expectException(DomainException::class);
        StreamId::fromString($frame);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function component_provider(): iterable
    {
        yield 'separator in name' => ['a:b', 'c', 'stream:v1:YTpi:Yw=='];
        yield 'separator in identifier' => ['a', 'b:c', 'stream:v1:YQ==:Yjpj'];
        yield 'whitespace and controls' => [" \t\r\n", "\0\x01", 'stream:v1:IAkNCg==:AAE='];
        yield 'invalid UTF-8' => ["\xff\xfe", "\x80", 'stream:v1://4=:gA=='];
        yield 'full Base64 alphabet' => ["\xfb\xff\xff", 'x', 'stream:v1:+///:eA=='];
        yield 'unicode bytes' => ['é', 'é', 'stream:v1:w6k=:ZcyB'];
        yield 'numeric looking' => ['01', '1e2', 'stream:v1:MDE=:MWUy'];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function wrong_type_provider(): iterable
    {
        yield 'null' => [null];
        yield 'number' => [123];
        yield 'array' => [['Order', '123']];
        yield 'canonical string' => ['stream:v1:T3JkZXI=:MTIz'];
        yield 'other value with same representation' => [StringObject::fromString('stream:v1:T3JkZXI=:MTIz')];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid_frame_provider(): iterable
    {
        foreach ([
            'empty frame' => '',
            'wrong prefix' => 'Stream:v1:YQ==:Yg==',
            'unsupported version' => 'stream:v2:YQ==:Yg==',
            'missing version' => 'stream:YQ==:Yg==',
            'extra component' => 'stream:v1:YQ==:Yg==:Yw==',
            'empty name' => 'stream:v1::Yg==',
            'empty identifier' => 'stream:v1:YQ==:',
            'invalid name encoding' => 'stream:v1:!!!!:Yg==',
            'invalid identifier encoding' => 'stream:v1:YQ==:!!!!',
            'missing padding' => 'stream:v1:YQ:Yg==',
            'extra padding' => 'stream:v1:YQ===:Yg==',
            'nonzero padding bits' => 'stream:v1:YR==:Yg==',
            'URL-safe alphabet' => 'stream:v1:-___:Yg==',
            'component space' => 'stream:v1:Y Q==:Yg==',
            'leading space' => ' stream:v1:YQ==:Yg==',
            'trailing newline' => "stream:v1:YQ==:Yg==\n",
            'component newline' => "stream:v1:YQ==:\nYg==",
            'raw binary' => "stream:v1:YQ==:\xff",
        ] as $name => $frame) {
            yield $name => [$frame];
        }
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
