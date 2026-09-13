<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\CodeIgniter;

use CodeIgniter\Config\Services;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\QueuePushResult;
use DateTimeImmutable;
use Fight\Common\Adapter\Messaging\CodeIgniter\CommandMessageJob;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueCommandBus;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(CommandMessageJob::class)]
#[CoversClass(QueueCommandBus::class)]
final class QueueCommandBusTest extends UnitTestCase
{
    public function test_that_dispatches_complete_command_messages_and_rejects_queue_failures(): void
    {
        $message = new CommandMessage(
            MessageId::fromString('018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e'),
            new DateTimeImmutable('2026-08-29 12:34:56.123456+00:00'),
            new QueueCommandBusTestCommand('command-73'),
            Meta::create(['trace_id' => 'trace-73'])
        );
        $queue = $this->mock(QueueInterface::class);
        $queue->shouldReceive('push')->once()->with('commands', 'fight-command', [
            'kind'    => 'command',
            'message' => $message->arraySerialize()
        ])->andReturn(QueuePushResult::success(73));

        (new QueueCommandBus($queue, 'commands', 'fight-command'))->dispatch($message);

        $failedQueue = $this->mock(QueueInterface::class);
        $failedQueue->shouldReceive('push')->once()->andReturn(QueuePushResult::failure('unavailable'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CodeIgniter Queue could not submit command message: unavailable');
        (new QueueCommandBus($failedQueue, 'commands', 'fight-command'))->execute(
            new QueueCommandBusTestCommand('command-73')
        );
    }

    public function test_that_job_validates_and_hands_the_reconstituted_message_to_its_service(): void
    {
        $message = CommandMessage::create(new QueueCommandBusTestCommand('command-73'));
        $bus = $this->mock(SynchronousCommandBus::class);
        $bus->shouldReceive('dispatch')->once()->withArgs(static function (CommandMessage $actual) use ($message): bool {
            self::assertSame($message->arraySerialize(), $actual->arraySerialize());

            return true;
        });
        Services::override(CommandMessageJob::HANDLER_SERVICE, new CommandMessageHandler($bus));

        try {
            (new CommandMessageJob([
                'kind'    => 'command',
                'message' => $message->arraySerialize()
            ]))->process();
        } finally {
            Services::reset(false);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Queued payload kind must be command.');
        (new CommandMessageJob(['kind' => 'event', 'message' => []]))->process();
    }

    public function test_that_job_rejects_invalid_messages_and_handler_services(): void
    {
        try {
            (new CommandMessageJob(['kind' => 'command', 'message' => 'invalid']))->process();
            self::fail('Expected an invalid payload message to be rejected.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('Queued payload message must be an array.', $error->getMessage());
        }

        Services::override(CommandMessageJob::HANDLER_SERVICE, new \stdClass());
        try {
            (new CommandMessageJob(['kind' => 'command', 'message' => []]))->process();
            self::fail('Expected an invalid handler service to be rejected.');
        } catch (RuntimeException $error) {
            self::assertSame(
                'CodeIgniter service "fightCommandMessageHandler" must resolve to '.CommandMessageHandler::class.'.',
                $error->getMessage()
            );
        } finally {
            Services::reset(false);
        }
    }
}

/**
 * Class QueueCommandBusTestCommand
 */
final readonly class QueueCommandBusTestCommand implements Command
{
    /**
     * Constructs QueueCommandBusTestCommand
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
