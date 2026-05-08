<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Command\Async;

use Fight\Common\Adapter\Messaging\Command\Async\MessengerCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

#[CoversClass(MessengerCommandBus::class)]
class MessengerCommandBusTest extends UnitTestCase
{
    public function test_that_execute_wraps_command_in_message_and_sends(): void
    {
        $command = new SampleCommand('test');

        $sender = $this->mock(SenderInterface::class);
        $sender->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (Envelope $envelope): Envelope {
                self::assertInstanceOf(CommandMessage::class, $envelope->getMessage());

                return $envelope;
            });

        $bus = new MessengerCommandBus($sender);

        $bus->execute($command);
    }

    public function test_that_dispatch_sends_envelope_via_sender(): void
    {
        $commandMessage = CommandMessage::create(new SampleCommand('dispatch'));

        $sender = $this->mock(SenderInterface::class);
        $sender->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (Envelope $envelope) use ($commandMessage): Envelope {
                self::assertSame($commandMessage, $envelope->getMessage());

                return $envelope;
            });

        $bus = new MessengerCommandBus($sender);

        $bus->dispatch($commandMessage);
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
