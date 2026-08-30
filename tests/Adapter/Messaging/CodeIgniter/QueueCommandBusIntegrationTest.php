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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RuntimeException;
use Throwable;

#[CoversClass(QueueCommandBus::class)]
#[CoversClass(CommandMessageJob::class)]
final class QueueCommandBusIntegrationTest extends UnitTestCase
{
    public function test_that_command_producer_and_job_failures_remain_visible(): void
    {
        $queue = $this->mock(QueueInterface::class);
        $queue->shouldReceive('push')->once()->andReturn(QueuePushResult::success(73));
        (new QueueCommandBus($queue, 'commands', 'fight-command'))->execute(
            new QueueCommandBusIntegrationCommand('command-73', 3)
        );

        $failedQueue = $this->mock(QueueInterface::class);
        $failedQueue->shouldReceive('push')->once()->andReturn(QueuePushResult::failure());

        try {
            (new QueueCommandBus($failedQueue, 'commands', 'fight-command'))->execute(
                new QueueCommandBusIntegrationCommand('command-73', 3)
            );
            self::fail('Expected the Queue push failure to escape.');
        } catch (RuntimeException $failure) {
            self::assertSame(
                'CodeIgniter Queue could not submit command message: unknown queue failure',
                $failure->getMessage()
            );
        }

        try {
            (new CommandMessageJob(['kind' => 'command', 'message' => 'invalid']))->process();
            self::fail('Expected a non-array command message to be rejected.');
        } catch (InvalidArgumentException $failure) {
            self::assertSame('Queued payload message must be an array.', $failure->getMessage());
        }

        Services::override(CommandMessageJob::HANDLER_SERVICE, new \stdClass());

        try {
            (new CommandMessageJob(['kind' => 'command', 'message' => []]))->process();
            self::fail('Expected an invalid command handler service to be rejected.');
        } catch (RuntimeException $failure) {
            self::assertSame(
                'CodeIgniter service "fightCommandMessageHandler" must resolve to '.CommandMessageHandler::class.'.',
                $failure->getMessage()
            );
        } finally {
            Services::reset(false);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_database_queue_retries_the_exact_command_message_through_the_native_lifecycle(): void
    {
        $recordingBus = new QueueCommandBusRecordingSynchronousBus();
        Services::override(
            CommandMessageJob::HANDLER_SERVICE,
            new CommandMessageHandler($recordingBus),
        );
        $fixture = DatabaseQueueFixture::boot([
            'fight-command' => CommandMessageJob::class,
        ]);

        try {
            $message = new CommandMessage(
                MessageId::fromString('018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e'),
                new DateTimeImmutable('2026-08-29 12:34:56.123456+00:00'),
                new QueueCommandBusIntegrationCommand('command-73', 3),
                Meta::create(['trace_id' => 'trace-73', 'attempt' => 1]),
            );
            $commandBus = new QueueCommandBus($fixture->queue, 'commands', 'fight-command');

            $commandBus->dispatch($message);
            $first = $fixture->queue->pop('commands', ['default']);

            self::assertNotNull($first);
            self::assertSame('fight-command', $first->payload['job']);
            self::assertSame('command', $first->payload['data']['kind']);
            self::assertSame($message->arraySerialize(), $first->payload['data']['message']);
            (new CommandMessageJob($first->payload['data']))->process();
            self::assertTrue($fixture->queue->done($first));
            self::assertSame([$message->arraySerialize()], array_map(
                static fn (CommandMessage $handled): array => $handled->arraySerialize(),
                $recordingBus->messages,
            ));

            $handlerFailure = new RuntimeException('synchronous handler failed');
            Services::override(
                CommandMessageJob::HANDLER_SERVICE,
                new CommandMessageHandler(new QueueCommandBusFailingSynchronousBus($handlerFailure)),
            );
            $commandBus->dispatch($message);
            $failed = $fixture->queue->pop('commands', ['default']);

            self::assertNotNull($failed);
            try {
                (new CommandMessageJob($failed->payload['data']))->process();
                self::fail('Expected the synchronous command handler failure to escape for native retry handling.');
            } catch (Throwable $error) {
                self::assertSame($handlerFailure, $error);
                self::assertTrue($fixture->queue->failed($failed, $error, true));
            }

            $failedJobs = $fixture->queue->listFailed('commands');
            self::assertCount(1, $failedJobs);
            self::assertSame('fight-command', $failedJobs[0]->payload['job']);
            self::assertSame($message->arraySerialize(), $failedJobs[0]->payload['data']['message']);
            self::assertSame(1, $fixture->queue->retry(null, 'commands'));

            Services::override(
                CommandMessageJob::HANDLER_SERVICE,
                new CommandMessageHandler($recordingBus),
            );
            $retry = $fixture->queue->pop('commands', ['default']);

            self::assertNotNull($retry);
            self::assertSame('fight-command', $retry->payload['job']);
            self::assertSame($message->arraySerialize(), $retry->payload['data']['message']);
            (new CommandMessageJob($retry->payload['data']))->process();
            self::assertTrue($fixture->queue->done($retry));
            self::assertSame([], $fixture->queue->listFailed('commands'));
            self::assertSame([
                $message->arraySerialize(),
                $message->arraySerialize(),
            ], array_map(
                static fn (CommandMessage $handled): array => $handled->arraySerialize(),
                $recordingBus->messages,
            ));

            try {
                (new CommandMessageJob([
                    'kind'    => 'event',
                    'message' => $message->arraySerialize(),
                ]))->process();
                self::fail('Expected a non-command queued payload to escape from the job.');
            } catch (InvalidArgumentException $error) {
                self::assertSame('Queued payload kind must be command.', $error->getMessage());
            }
        } finally {
            Services::reset(false);
            $fixture->close();
        }
    }
}

final readonly class QueueCommandBusIntegrationCommand implements Command
{
    public function __construct(private string $reference, private int $quantity)
    {
    }

    public static function fromArray(array $data): static
    {
        return new static($data['reference'], $data['quantity']);
    }

    public function toArray(): array
    {
        return ['reference' => $this->reference, 'quantity' => $this->quantity];
    }
}

final class QueueCommandBusRecordingSynchronousBus implements SynchronousCommandBus
{
    /** @var list<CommandMessage> */
    public array $messages = [];

    public function execute(Command $command): void
    {
        $this->dispatch(CommandMessage::create($command));
    }

    public function dispatch(CommandMessage $commandMessage): void
    {
        $this->messages[] = $commandMessage;
    }
}

final readonly class QueueCommandBusFailingSynchronousBus implements SynchronousCommandBus
{
    public function __construct(private RuntimeException $failure)
    {
    }

    public function execute(Command $command): void
    {
        throw $this->failure;
    }

    public function dispatch(CommandMessage $commandMessage): void
    {
        throw $this->failure;
    }
}
