<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Serialization;

use Override;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageType;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Serialization\JsonSerializer;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonSerializer::class)]
class JsonSerializerTest extends UnitTestCase
{
    private JsonSerializer $serializer;

    #[Override]
    protected function setUp(): void
    {
        $this->serializer = new JsonSerializer();
    }

    // -------------------------------------------------------------------------
    // serialize()
    // -------------------------------------------------------------------------

    public function test_that_serialize_returns_a_valid_json_string_from_a_command_message(): void
    {
        $message = CommandMessage::create(new SampleCommand('hello'));

        $json = $this->serializer->serialize($message);

        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('@', $decoded);
        self::assertArrayHasKey('$', $decoded);
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

    public function test_that_deserialize_throws_for_invalid_json(): void
    {
        $this->expectException(DomainException::class);
        $this->serializer->deserialize('not-valid-json{{}');
    }

    public function test_that_deserialize_throws_for_missing_required_keys(): void
    {
        $this->expectException(DomainException::class);
        $this->serializer->deserialize(json_encode(['other' => 'value']));
    }

    public function test_that_deserialize_throws_for_a_non_serializable_class(): void
    {
        $this->expectException(DomainException::class);
        $this->serializer->deserialize(json_encode(['@' => 'stdClass', '$' => []]));
    }
}
