<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Messaging;

use stdClass;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MessageId::class)]
class MessageIdTest extends UnitTestCase
{
    private const string KNOWN_UUID = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    private const string OTHER_UUID = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    public function test_that_generate_returns_a_new_instance_each_call(): void
    {
        $id1 = MessageId::generate();
        $id2 = MessageId::generate();

        self::assertNotSame($id1->toString(), $id2->toString());
    }

    public function test_that_from_string_creates_instance_from_valid_uuid_string(): void
    {
        $id = MessageId::fromString(self::KNOWN_UUID);

        self::assertSame(self::KNOWN_UUID, $id->toString());
    }

    public function test_that_from_string_throws_for_invalid_string(): void
    {
        $this->expectException(DomainException::class);
        MessageId::fromString('not-a-uuid');
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function test_that_compare_to_returns_zero_for_the_same_instance(): void
    {
        $id = MessageId::fromString(self::KNOWN_UUID);

        self::assertSame(0, $id->compareTo($id));
    }

    public function test_that_compare_to_returns_zero_for_equal_instances(): void
    {
        $id1 = MessageId::fromString(self::KNOWN_UUID);
        $id2 = MessageId::fromString(self::KNOWN_UUID);

        self::assertSame(0, $id1->compareTo($id2));
    }

    public function test_that_compare_to_returns_non_zero_for_different_instances(): void
    {
        $id1 = MessageId::fromString(self::KNOWN_UUID);
        $id2 = MessageId::fromString(self::OTHER_UUID);

        self::assertNotSame(0, $id1->compareTo($id2));
    }

    // -------------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------------

    public function test_that_equals_returns_true_for_the_same_instance(): void
    {
        $id = MessageId::fromString(self::KNOWN_UUID);

        self::assertTrue($id->equals($id));
    }

    public function test_that_equals_returns_true_for_same_value(): void
    {
        $id1 = MessageId::fromString(self::KNOWN_UUID);
        $id2 = MessageId::fromString(self::KNOWN_UUID);

        self::assertTrue($id1->equals($id2));
    }

    public function test_that_equals_returns_false_for_different_value(): void
    {
        $id1 = MessageId::fromString(self::KNOWN_UUID);
        $id2 = MessageId::fromString(self::OTHER_UUID);

        self::assertFalse($id1->equals($id2));
    }

    public function test_that_equals_returns_false_for_wrong_type(): void
    {
        self::assertFalse(MessageId::fromString(self::KNOWN_UUID)->equals(new stdClass()));
    }

    public function test_that_hash_value_returns_same_string_for_two_instances_from_the_same_string(): void
    {
        $id1 = MessageId::fromString(self::KNOWN_UUID);
        $id2 = MessageId::fromString(self::KNOWN_UUID);

        self::assertSame($id1->hashValue(), $id2->hashValue());
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    public function test_that_to_string_returns_the_uuid_string(): void
    {
        self::assertSame(self::KNOWN_UUID, MessageId::fromString(self::KNOWN_UUID)->toString());
    }

    public function test_that_cast_to_string_returns_the_uuid_string(): void
    {
        self::assertSame(self::KNOWN_UUID, (string) MessageId::fromString(self::KNOWN_UUID));
    }
}
