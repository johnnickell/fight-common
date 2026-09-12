<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Symfony;

use Fight\Common\Adapter\Messaging\Symfony\MessengerCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

#[CoversClass(MessengerCommandBus::class)]
final class MessengerCommandBusTest extends UnitTestCase
{
    public function test_that_execute_and_dispatch_send_complete_command_messages(): void
    {
        $message = CommandMessage::create(new MessengerCommandBusTestCommand('command-73'));
        $sender = $this->mock(SenderInterface::class);
        $sender->shouldReceive('send')->twice()->andReturnUsing(static function (Envelope $envelope) use ($message): Envelope {
            self::assertInstanceOf(CommandMessage::class, $envelope->getMessage());

            return $envelope;
        });
        $bus = new MessengerCommandBus($sender);
        $bus->execute(new MessengerCommandBusTestCommand('execute-73'));
        $bus->dispatch($message);
    }
}

/**
 * Class MessengerCommandBusTestCommand
 */
final readonly class MessengerCommandBusTestCommand implements Command
{
    /**
     * Constructs MessengerCommandBusTestCommand
     */
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
