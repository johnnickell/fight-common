<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Handler;

use DateTimeImmutable;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CommandMessageHandler::class)]
final class CommandMessageHandlerTest extends UnitTestCase
{
    public function test_that_invoke_forwards_the_complete_original_command_envelope(): void
    {
        $message = new CommandMessage(
            MessageId::generate(),
            new DateTimeImmutable('2026-08-24 12:34:56.123456+00:00'),
            new CommandMessageHandlerSampleCommand('preserved-value'),
            Meta::create(['trace_id' => 'trace-51', 'nested' => ['attempt' => 2]]),
        );

        $commandBus = $this->mock(SynchronousCommandBus::class);
        $commandBus->shouldReceive('dispatch')->once()->with(
            \Mockery::on(static function (CommandMessage $received) use ($message): bool {
                self::assertSame($message, $received);
                self::assertSame($message->id(), $received->id());
                self::assertSame($message->timestamp(), $received->timestamp());
                self::assertSame($message->payload(), $received->payload());
                self::assertSame($message->payloadType()->toString(), $received->payloadType()->toString());
                self::assertSame($message->meta()->toArray(), $received->meta()->toArray());

                return true;
            }),
        );

        (new CommandMessageHandler($commandBus))($message);
    }
}

final readonly class CommandMessageHandlerSampleCommand implements Command
{
    public function __construct(private string $value)
    {
    }

    public static function fromArray(array $data): static
    {
        return new static($data['value']);
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
