<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Symfony\Serializer;

use Fight\Common\Adapter\Messaging\Symfony\Serializer\SymfonyMessageSerializer;
use Fight\Common\Application\Serialization\JsonSerializer;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

#[CoversClass(SymfonyMessageSerializer::class)]
final class SymfonyMessageSerializerTest extends UnitTestCase
{
    public function test_that_encodes_and_decodes_the_complete_messenger_envelope(): void
    {
        $serializer = new SymfonyMessageSerializer(new JsonSerializer());
        $envelope = new Envelope(CommandMessage::create(new SymfonyMessageSerializerTestCommand('command-73')));

        $decoded = $serializer->decode($serializer->encode($envelope));

        self::assertInstanceOf(CommandMessage::class, $decoded->getMessage());
    }

    public function test_that_rejects_unknown_unserialized_classes(): void
    {
        $this->expectException(MessageDecodingFailedException::class);
        SymfonyMessageSerializer::handleUnserializeCallback('NotAnAvailableMessageClass');
    }
}

final readonly class SymfonyMessageSerializerTestCommand implements Command
{
    public function __construct(private string $reference)
    {
    }

    public static function fromArray(array $data): static
    {
        return new static($data['reference']);
    }

    public function toArray(): array
    {
        return ['reference' => $this->reference];
    }
}
