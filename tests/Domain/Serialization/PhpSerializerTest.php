<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Serialization;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageType;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Serialization\PhpSerializer;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PhpSerializer::class)]
class PhpSerializerTest extends UnitTestCase
{
    private PhpSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new PhpSerializer();
    }

    // -------------------------------------------------------------------------
    // serialize()
    // -------------------------------------------------------------------------

    public function test_that_serialize_returns_a_valid_php_serialized_string(): void
    {
        $message = CommandMessage::create(new SampleCommand('hello'));

        $serialized = $this->serializer->serialize($message);

        self::assertIsString($serialized);
        $data = unserialize($serialized);
        self::assertIsArray($data);
        self::assertArrayHasKey('@', $data);
        self::assertArrayHasKey('$', $data);
    }

    // -------------------------------------------------------------------------
    // deserialize() — happy path
    // -------------------------------------------------------------------------

    public function test_that_deserialize_round_trips_a_command_message(): void
    {
        $original = CommandMessage::create(new SampleCommand('test-value'));

        $deserialized = $this->serializer->deserialize($this->serializer->serialize($original));

        self::assertInstanceOf(CommandMessage::class, $deserialized);
        self::assertSame($original->id()->toString(), $deserialized->id()->toString());
        self::assertSame(MessageType::COMMAND, $deserialized->type());
        self::assertSame(['value' => 'test-value'], $deserialized->payload()->toArray());
    }

    public function test_that_serialize_and_deserialize_round_trip_an_event_message(): void
    {
        $original = EventMessage::create(new SampleEvent('event-data'));

        $deserialized = $this->serializer->deserialize($this->serializer->serialize($original));

        self::assertInstanceOf(EventMessage::class, $deserialized);
        self::assertSame($original->id()->toString(), $deserialized->id()->toString());
        self::assertSame(MessageType::EVENT, $deserialized->type());
        self::assertSame(['value' => 'event-data'], $deserialized->payload()->toArray());
    }

    public function test_that_serialize_and_deserialize_round_trip_a_query_message(): void
    {
        $original = QueryMessage::create(new SampleQuery('query-data'));

        $deserialized = $this->serializer->deserialize($this->serializer->serialize($original));

        self::assertInstanceOf(QueryMessage::class, $deserialized);
        self::assertSame($original->id()->toString(), $deserialized->id()->toString());
        self::assertSame(MessageType::QUERY, $deserialized->type());
        self::assertSame(['value' => 'query-data'], $deserialized->payload()->toArray());
    }

    // -------------------------------------------------------------------------
    // deserialize() — error cases
    // -------------------------------------------------------------------------

    public function test_that_deserialize_throws_for_invalid_serialized_data(): void
    {
        $this->expectException(DomainException::class);
        $this->serializer->deserialize('not-valid-php-serialized-data');
    }

    public function test_that_deserialize_throws_for_missing_required_keys(): void
    {
        $this->expectException(DomainException::class);
        $this->serializer->deserialize(serialize(['other' => 'value']));
    }

    public function test_that_deserialize_throws_for_a_non_serializable_class(): void
    {
        $this->expectException(DomainException::class);
        $this->serializer->deserialize(serialize(['@' => 'stdClass', '$' => []]));
    }
}
