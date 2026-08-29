<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Laravel;

use DateTimeImmutable;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Laravel\QueuedCommandMessage;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\Messaging\Laravel\RecordingSyncQueue;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Bus\Dispatcher as BusDispatcher;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcherContract;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Events\Dispatcher as EventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueuedCommandMessage::class)]
final class QueuedCommandMessageIntegrationTest extends UnitTestCase
{
    public function test_that_sync_queue_serializes_the_complete_command_envelope_and_submits_it_after_commit(): void
    {
        $container = new Container();
        $container->instance(ContainerContract::class, $container);
        $container->instance(EventDispatcherContract::class, new EventDispatcher($container));
        $container->instance(DispatcherContract::class, new BusDispatcher($container));
        $transactions = new DatabaseTransactionsManager();
        $container->instance('db.transactions', $transactions);
        $commandBus = new QueuedCommandMessageIntegrationBus();
        $container->instance(
            CommandMessageHandler::class,
            new CommandMessageHandler($commandBus),
        );
        $queue = new RecordingSyncQueue();
        $queue->setContainer($container);
        $queue->setConnectionName('sync');
        $transactions->begin('fight-common', 1);

        $job = new QueuedCommandMessage(new CommandMessage(
            MessageId::fromString('018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e'),
            new DateTimeImmutable('2026-08-28 12:34:56.123456+00:00'),
            new QueuedCommandMessageIntegrationCommand('command-70', 3),
            Meta::create(['trace_id' => 'trace-70', 'attempt' => 1]),
        ));

        /** @phpstan-ignore-next-line Laravel queues accept object jobs despite the inherited contract annotation */
        $queue->push($job);

        self::assertSame([], $commandBus->messages);

        $transactions->commit('fight-common', 1, 0);

        $payload = json_decode($queue->lastPayload(), true, flags: JSON_THROW_ON_ERROR);
        $serializedJob = $payload['data']['command'] ?? null;
        self::assertIsString($serializedJob);
        $reconstitutedJob = unserialize(
            $serializedJob,
            ['allowed_classes' => [QueuedCommandMessage::class]],
        );
        self::assertInstanceOf(QueuedCommandMessage::class, $reconstitutedJob);
        self::assertNotSame($job, $reconstitutedJob);

        self::assertCount(1, $commandBus->messages);
        $message = current($commandBus->messages);
        self::assertInstanceOf(CommandMessage::class, $message);
        self::assertSame('018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e', $message->id()->toString());
        self::assertSame('2026-08-28 12:34:56.123456', $message->timestamp()->format('Y-m-d H:i:s.u'));
        self::assertSame(
            'Fight.Test.Common.Adapter.Messaging.Laravel.QueuedCommandMessageIntegrationCommand',
            $message->payloadType()->toString(),
        );
        self::assertSame(['reference' => 'command-70', 'quantity' => 3], $message->payload()->toArray());
        self::assertSame(['trace_id' => 'trace-70', 'attempt' => 1], $message->meta()->toArray());
    }
}

final readonly class QueuedCommandMessageIntegrationCommand implements Command
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

final class QueuedCommandMessageIntegrationBus implements SynchronousCommandBus
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
