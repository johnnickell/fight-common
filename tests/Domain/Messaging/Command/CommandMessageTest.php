<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Messaging\Command;

use DateTimeImmutable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\BaseMessage;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\MessageType;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CommandMessage::class)]
#[CoversClass(BaseMessage::class)]
class CommandMessageTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    public function test_that_create_returns_a_correctly_typed_instance(): void
    {
        $message = CommandMessage::create(new SampleCommand('hello'));

        self::assertInstanceOf(CommandMessage::class, $message);
        self::assertSame(MessageType::COMMAND, $message->type());
    }

    public function test_that_create_returns_instance_with_empty_meta(): void
    {
        $message = CommandMessage::create(new SampleCommand());

        self::assertTrue($message->meta()->isEmpty());
    }

    // -------------------------------------------------------------------------
    // Accessors (BaseMessage)
    // -------------------------------------------------------------------------

    public function test_that_id_returns_the_message_id(): void
    {
        $id = MessageId::generate();
        $message = new CommandMessage($id, new DateTimeImmutable(), new SampleCommand(), Meta::create());

        self::assertSame($id, $message->id());
    }

    public function test_that_timestamp_returns_the_date_time(): void
    {
        $timestamp = new DateTimeImmutable('2024-01-01 00:00:00');
        $message = new CommandMessage(MessageId::generate(), $timestamp, new SampleCommand(), Meta::create());

        self::assertSame($timestamp, $message->timestamp());
    }

    public function test_that_payload_returns_the_command(): void
    {
        $command = new SampleCommand('data');
        $message = CommandMessage::create($command);

        self::assertSame($command, $message->payload());
    }

    public function test_that_payload_type_returns_type_matching_command_class(): void
    {
        $command = new SampleCommand();
        $message = CommandMessage::create($command);

        self::assertSame(SampleCommand::class, $message->payloadType()->toClassName());
    }

    // -------------------------------------------------------------------------
    // Serialization (BaseMessage)
    // -------------------------------------------------------------------------

    public function test_that_to_array_contains_expected_keys(): void
    {
        $message = CommandMessage::create(new SampleCommand('val'));
        $array = $message->toArray();

        self::assertArrayHasKey('id', $array);
        self::assertArrayHasKey('type', $array);
        self::assertArrayHasKey('timestamp', $array);
        self::assertArrayHasKey('payload_type', $array);
        self::assertArrayHasKey('payload', $array);
        self::assertArrayHasKey('meta', $array);
        self::assertSame('command', $array['type']);
    }

    public function test_that_json_serialize_returns_same_as_to_array(): void
    {
        $message = CommandMessage::create(new SampleCommand());

        self::assertSame($message->toArray(), $message->jsonSerialize());
    }

    public function test_that_array_serialize_returns_same_as_to_array(): void
    {
        $message = CommandMessage::create(new SampleCommand());

        self::assertSame($message->toArray(), $message->arraySerialize());
    }

    public function test_that_to_string_returns_json_representation(): void
    {
        $message = CommandMessage::create(new SampleCommand('str'));

        self::assertSame(json_encode($message->toArray(), JSON_UNESCAPED_SLASHES), $message->toString());
    }

    public function test_that_cast_to_string_returns_json_representation(): void
    {
        $message = CommandMessage::create(new SampleCommand());

        self::assertSame($message->toString(), (string) $message);
    }

    // -------------------------------------------------------------------------
    // Comparison (BaseMessage)
    // -------------------------------------------------------------------------

    public function test_that_compare_to_returns_zero_for_the_same_instance(): void
    {
        $message = CommandMessage::create(new SampleCommand());

        self::assertSame(0, $message->compareTo($message));
    }

    public function test_that_compare_to_returns_zero_for_equal_id(): void
    {
        $id = MessageId::generate();
        $ts = new DateTimeImmutable();
        $m1 = new CommandMessage($id, $ts, new SampleCommand(), Meta::create());
        $m2 = new CommandMessage($id, $ts, new SampleCommand(), Meta::create());

        self::assertSame(0, $m1->compareTo($m2));
    }

    public function test_that_compare_to_returns_non_zero_for_different_ids(): void
    {
        $m1 = CommandMessage::create(new SampleCommand());
        $m2 = CommandMessage::create(new SampleCommand());

        self::assertNotSame(0, $m1->compareTo($m2));
    }

    public function test_that_equals_returns_true_for_same_instance(): void
    {
        $message = CommandMessage::create(new SampleCommand());

        self::assertTrue($message->equals($message));
    }

    public function test_that_equals_returns_true_for_same_id(): void
    {
        $id = MessageId::generate();
        $ts = new DateTimeImmutable();
        $m1 = new CommandMessage($id, $ts, new SampleCommand(), Meta::create());
        $m2 = new CommandMessage($id, $ts, new SampleCommand(), Meta::create());

        self::assertTrue($m1->equals($m2));
    }

    public function test_that_equals_returns_false_for_different_id(): void
    {
        $m1 = CommandMessage::create(new SampleCommand());
        $m2 = CommandMessage::create(new SampleCommand());

        self::assertFalse($m1->equals($m2));
    }

    public function test_that_equals_returns_false_for_different_type(): void
    {
        $message = CommandMessage::create(new SampleCommand());

        self::assertFalse($message->equals(new \stdClass()));
    }

    public function test_that_hash_value_contains_class_name_and_id(): void
    {
        $message = CommandMessage::create(new SampleCommand());
        $hash = $message->hashValue();

        self::assertStringContainsString('CommandMessage', $hash);
        self::assertStringContainsString($message->id()->hashValue(), $hash);
    }

    // -------------------------------------------------------------------------
    // withMeta
    // -------------------------------------------------------------------------

    public function test_that_with_meta_returns_new_instance_with_replaced_meta(): void
    {
        $message = CommandMessage::create(new SampleCommand());
        $newMeta = Meta::create(['key' => 'value']);

        $updated = $message->withMeta($newMeta);

        self::assertNotSame($message, $updated);
        self::assertSame('value', $updated->meta()->get('key'));
        self::assertFalse($message->meta()->has('key'));
    }

    public function test_that_with_meta_preserves_id_and_type(): void
    {
        $message = CommandMessage::create(new SampleCommand());
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
        $message = new CommandMessage($id, new DateTimeImmutable(), new SampleCommand(), Meta::create(['a' => 1]));

        $updated = $message->mergeMeta(Meta::create(['b' => 2]));

        self::assertNotSame($message, $updated);
        self::assertSame(1, $updated->meta()->get('a'));
        self::assertSame(2, $updated->meta()->get('b'));
    }

    public function test_that_merge_meta_does_not_mutate_original(): void
    {
        $message = CommandMessage::create(new SampleCommand());

        $message->mergeMeta(Meta::create(['extra' => true]));

        self::assertFalse($message->meta()->has('extra'));
    }

    // -------------------------------------------------------------------------
    // arrayDeserialize round-trip
    // -------------------------------------------------------------------------

    public function test_that_array_deserialize_round_trips_correctly_from_to_array(): void
    {
        $command = new SampleCommand('round-trip');
        $original = CommandMessage::create($command);

        $deserialized = CommandMessage::arrayDeserialize($original->toArray());

        self::assertSame($original->id()->toString(), $deserialized->id()->toString());
        self::assertSame(MessageType::COMMAND, $deserialized->type());
        self::assertSame($command->toArray(), $deserialized->payload()->toArray());
    }

    // -------------------------------------------------------------------------
    // arrayDeserialize error cases
    // -------------------------------------------------------------------------

    public function test_that_array_deserialize_throws_for_missing_id_key(): void
    {
        $this->expectException(DomainException::class);
        CommandMessage::arrayDeserialize([
            'type'         => 'command',
            'timestamp'    => '1746748800',
            'meta'         => [],
            'payload_type' => 'Fight.Test.Common.Domain.Messaging.Command.SampleCommand',
            'payload'      => [],
        ]);
    }

    public function test_that_array_deserialize_throws_for_missing_payload_key(): void
    {
        $this->expectException(DomainException::class);
        CommandMessage::arrayDeserialize([
            'id'           => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'type'         => 'command',
            'timestamp'    => '1746748800',
            'meta'         => [],
            'payload_type' => 'Fight.Test.Common.Domain.Messaging.Command.SampleCommand',
        ]);
    }

    public function test_that_array_deserialize_throws_for_wrong_message_type(): void
    {
        $this->expectException(DomainException::class);
        CommandMessage::arrayDeserialize([
            'id'           => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'type'         => 'event',
            'timestamp'    => '1746748800',
            'meta'         => [],
            'payload_type' => 'Fight.Test.Common.Domain.Messaging.Command.SampleCommand',
            'payload'      => [],
        ]);
    }
}

class SampleCommand implements Command
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
