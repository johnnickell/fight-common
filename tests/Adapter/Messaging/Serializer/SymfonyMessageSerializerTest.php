<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Serializer;

use stdClass;
use Fight\Common\Adapter\Messaging\Serializer\SymfonyMessageSerializer;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Serialization\JsonSerializer;
use Fight\Common\Domain\Serialization\Serializer as DomainSerializer;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

#[CoversClass(SymfonyMessageSerializer::class)]
class SymfonyMessageSerializerTest extends UnitTestCase
{
    private DomainSerializer $domainSerializer;
    private SymfonyMessageSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domainSerializer = new JsonSerializer();
        $this->serializer = new SymfonyMessageSerializer($this->domainSerializer);
    }

    public function test_that_encode_returns_body_and_stamp_headers(): void
    {
        $message = CommandMessage::create(new SampleCommand('test-data'));
        $envelope = new Envelope($message, [new BusNameStamp('command_bus')]);

        $encoded = $this->serializer->encode($envelope);

        self::assertArrayHasKey('body', $encoded);
        self::assertArrayHasKey('headers', $encoded);
        self::assertStringContainsString('CommandMessage', $encoded['body']);
        self::assertArrayHasKey('X-Message-Stamp-BusNameStamp', $encoded['headers']);
    }

    public function test_that_encode_strips_non_sendable_stamps(): void
    {
        $message = CommandMessage::create(new SampleCommand());
        $envelope = new Envelope($message, [
            new SentStamp('SomeSender'),
            new BusNameStamp('test_bus'),
        ]);

        $encoded = $this->serializer->encode($envelope);

        self::assertArrayNotHasKey('X-Message-Stamp-SentStamp', $encoded['headers']);
        self::assertArrayHasKey('X-Message-Stamp-BusNameStamp', $encoded['headers']);
    }

    public function test_that_decode_returns_envelope_with_message_and_stamps(): void
    {
        $message = CommandMessage::create(new SampleCommand('round-trip'));
        $originalEnvelope = new Envelope($message, [new BusNameStamp('test_bus')]);

        $encoded = $this->serializer->encode($originalEnvelope);
        $decodedEnvelope = $this->serializer->decode($encoded);

        $decodedMessage = $decodedEnvelope->getMessage();

        self::assertInstanceOf(CommandMessage::class, $decodedMessage);
        self::assertSame('round-trip', $decodedMessage->payload()->toArray()['value']);

        $busNameStamp = $decodedEnvelope->last(BusNameStamp::class);

        self::assertInstanceOf(BusNameStamp::class, $busNameStamp);
        self::assertSame('test_bus', $busNameStamp->getBusName());
    }

    public function test_that_decode_throws_for_missing_body(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer->decode(['headers' => []]);
    }

    public function test_that_decode_throws_for_invalid_body(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer->decode([
            'body' => 'invalid json',
            'headers' => [],
        ]);
    }

    public function test_that_decode_handles_missing_headers(): void
    {
        $message = CommandMessage::create(new SampleCommand('no-headers'));
        $originalEnvelope = new Envelope($message);

        $encoded = $this->serializer->encode($originalEnvelope);
        unset($encoded['headers']);

        $decodedEnvelope = $this->serializer->decode($encoded);

        self::assertInstanceOf(CommandMessage::class, $decodedEnvelope->getMessage());
    }

    public function test_that_decode_throws_for_corrupted_stamp_data(): void
    {
        $encoded = [
            'body' => '{"@":"Fight.Common.Domain.Messaging.Command.CommandMessage","$":{"id":"test","type":"command","timestamp":"1746748800","payload_type":"Fight.Test.Common.Adapter.Messaging.Serializer.SampleCommand","payload":{"value":"test"},"meta":{}}}',
            'headers' => [
                'X-Message-Stamp-BusNameStamp' => 'invalid-serialized-data',
            ],
        ];

        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer->decode($encoded);
    }

    public function test_that_encode_throws_for_non_serializable_message(): void
    {
        $envelope = new Envelope(new stdClass());

        $this->expectException(RuntimeException::class);

        $this->serializer->encode($envelope);
    }

    public function test_that_decode_round_trips_with_non_sendable_stamps_stripped(): void
    {
        $message = CommandMessage::create(new SampleCommand('stripped'));
        $envelopeWithNonSendable = new Envelope($message, [
            new SentStamp('MySender'),
            new BusNameStamp('main_bus'),
        ]);

        $encoded = $this->serializer->encode($envelopeWithNonSendable);
        $decodedEnvelope = $this->serializer->decode($encoded);

        self::assertSame('stripped', $decodedEnvelope->getMessage()->payload()->toArray()['value']);

        self::assertNull($decodedEnvelope->last(SentStamp::class));

        $busStamp = $decodedEnvelope->last(BusNameStamp::class);
        self::assertInstanceOf(BusNameStamp::class, $busStamp);
    }

    public function test_that_decode_throws_when_body_is_empty_string(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer->decode([
            'body' => '',
            'headers' => [],
        ]);
    }
}

class SampleCommand implements Command
{
    public function __construct(private readonly string $value = '')
    {
    }

    public static function fromArray(array $data): static
    {
        return new static($data['value'] ?? '');
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
