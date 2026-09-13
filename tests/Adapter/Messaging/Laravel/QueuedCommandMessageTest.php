<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Laravel;

use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Laravel\QueuedCommandMessage;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueuedCommandMessage::class)]
final class QueuedCommandMessageTest extends UnitTestCase
{
    public function test_that_handle_reconstitutes_the_complete_command_message(): void
    {
        $message = CommandMessage::create(new QueuedCommandMessageTestCommand('command-73'));
        $bus = $this->mock(SynchronousCommandBus::class);
        $bus->shouldReceive('dispatch')->once()->withArgs(static function (CommandMessage $actual) use ($message): bool {
            self::assertSame($message->arraySerialize(), $actual->arraySerialize());

            return true;
        });

        (new QueuedCommandMessage($message))->handle(new CommandMessageHandler($bus));
    }
}

/**
 * Class QueuedCommandMessageTestCommand
 */
final readonly class QueuedCommandMessageTestCommand implements Command
{
    /**
     * Constructs QueuedCommandMessageTestCommand
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
