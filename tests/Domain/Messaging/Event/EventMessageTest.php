<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Messaging\Event;

use DateTimeImmutable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\MessageType;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventMessage::class)]
class EventMessageTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    public function test_that_create_returns_a_correctly_typed_instance(): void
    {
        $message = EventMessage::create(new SampleEvent('hello'));

        self::assertInstanceOf(EventMessage::class, $message);
        self::assertSame(MessageType::EVENT, $message->type());
    }

    public function test_that_create_returns_instance_with_empty_meta(): void
    {
        $message = EventMessage::create(new SampleEvent());

        self::assertTrue($message->meta()->isEmpty());
    }

    // -------------------------------------------------------------------------
    // withMeta
    // -------------------------------------------------------------------------

    public function test_that_with_meta_returns_new_instance_with_replaced_meta(): void
    {
        $message = EventMessage::create(new SampleEvent());
        $newMeta = Meta::create(['key' => 'value']);

        $updated = $message->withMeta($newMeta);

        self::assertNotSame($message, $updated);
        self::assertSame('value', $updated->meta()->get('key'));
        self::assertFalse($message->meta()->has('key'));
    }

    public function test_that_with_meta_preserves_id_and_type(): void
    {
        $message = EventMessage::create(new SampleEvent());
        $updated = $message->withMeta(Meta::create(['x' => 1]));

        self::assertSame($message->id()->toString(), $updated->id()->toString());
        self::assertSame($message->type(), $updated->type());
    }

    // -------------------------------------------------------------------------
    // mergeMeta
    // -------------------------------------------------------------------------

    public function test_that_merge_meta_returns_new_instance_with_merged_meta(): void
    {
        $id = MessageId::generate();
        $message = new EventMessage($id, new DateTimeImmutable(), new SampleEvent(), Meta::create(['a' => 1]));

        $updated = $message->mergeMeta(Meta::create(['b' => 2]));

        self::assertNotSame($message, $updated);
        self::assertSame(1, $updated->meta()->get('a'));
        self::assertSame(2, $updated->meta()->get('b'));
    }

    public function test_that_merge_meta_does_not_mutate_original(): void
    {
        $message = EventMessage::create(new SampleEvent());

        $message->mergeMeta(Meta::create(['extra' => true]));

        self::assertFalse($message->meta()->has('extra'));
    }

    // -------------------------------------------------------------------------
    // arrayDeserialize round-trip
    // -------------------------------------------------------------------------

    public function test_that_array_deserialize_round_trips_correctly_from_to_array(): void
    {
        $event = new SampleEvent('round-trip');
        $original = EventMessage::create($event);

        $deserialized = EventMessage::arrayDeserialize($original->toArray());

        self::assertSame($original->id()->toString(), $deserialized->id()->toString());
        self::assertSame(MessageType::EVENT, $deserialized->type());
        self::assertSame($event->toArray(), $deserialized->payload()->toArray());
    }

    // -------------------------------------------------------------------------
    // arrayDeserialize error cases
    // -------------------------------------------------------------------------

    public function test_that_array_deserialize_throws_for_missing_id_key(): void
    {
        $this->expectException(DomainException::class);
        EventMessage::arrayDeserialize([
            'type'         => 'event',
            'timestamp'    => '1746748800',
            'meta'         => [],
            'payload_type' => 'Fight.Test.Common.Domain.Messaging.Event.SampleEvent',
            'payload'      => [],
        ]);
    }

    public function test_that_array_deserialize_throws_for_missing_payload_key(): void
    {
        $this->expectException(DomainException::class);
        EventMessage::arrayDeserialize([
            'id'           => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'type'         => 'event',
            'timestamp'    => '1746748800',
            'meta'         => [],
            'payload_type' => 'Fight.Test.Common.Domain.Messaging.Event.SampleEvent',
        ]);
    }

    public function test_that_array_deserialize_throws_for_wrong_message_type(): void
    {
        $this->expectException(DomainException::class);
        EventMessage::arrayDeserialize([
            'id'           => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'type'         => 'command',
            'timestamp'    => '1746748800',
            'meta'         => [],
            'payload_type' => 'Fight.Test.Common.Domain.Messaging.Event.SampleEvent',
            'payload'      => [],
        ]);
    }
}

class SampleEvent implements Event
{
    public function __construct(private readonly string $value = '') {}

    public static function fromArray(array $data): static
    {
        return new static($data['value'] ?? '');
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
