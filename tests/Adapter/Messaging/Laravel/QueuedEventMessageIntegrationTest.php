<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Laravel;

use DateTimeImmutable;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\Messaging\Laravel\QueuedEventMessage;
use Fight\Common\Domain\Messaging\Event\AllEvents;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Messaging\Laravel\RecordingSyncQueue;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Bus\Dispatcher as BusDispatcher;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcherContract;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Queue\Jobs\SyncJob;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(QueuedEventMessage::class)]
final class QueuedEventMessageIntegrationTest extends UnitTestCase
{
    public function test_that_sync_queue_preserves_the_complete_event_envelope_and_repeats_ordered_fan_out_on_retry(): void
    {
        $container = new Container();
        $container->instance(ContainerContract::class, $container);
        $container->instance(EventDispatcherContract::class, new EventDispatcher($container));
        $container->instance(DispatcherContract::class, new BusDispatcher($container));
        $transactions = new DatabaseTransactionsManager();
        $container->instance('db.transactions', $transactions);
        $calls = [];
        $dispatcher = new QueuedEventMessageIntegrationDispatcher();
        $dispatcher->addHandler(
            ClassName::underscore(QueuedEventMessageIntegrationEvent::class),
            static function (EventMessage $message) use (&$calls): void {
                $calls[] = ['event-high', $message->id()->toString(), $message->timestamp()->format('Y-m-d H:i:s.u'), $message->payloadType()->toString(), $message->payload()->toArray(), $message->meta()->toArray()];
                $message->meta()->set('listener_mutation', 'must-not-leak');
            },
            20
        );
        $dispatcher->addHandler(
            ClassName::underscore(QueuedEventMessageIntegrationEvent::class),
            static function (EventMessage $message) use (&$calls): void {
                $calls[] = ['event-low', $message->id()->toString(), $message->timestamp()->format('Y-m-d H:i:s.u'), $message->payloadType()->toString(), $message->payload()->toArray(), $message->meta()->toArray()];
            },
            10
        );
        $dispatcher->addHandler(
            ClassName::underscore(AllEvents::class),
            static function (EventMessage $message) use (&$calls): void {
                $calls[] = ['all-events', $message->id()->toString(), $message->timestamp()->format('Y-m-d H:i:s.u'), $message->payloadType()->toString(), $message->payload()->toArray(), $message->meta()->toArray()];
            }
        );
        $container->instance(EventMessageHandler::class, new EventMessageHandler($dispatcher));
        $queue = new RecordingSyncQueue();
        $queue->setContainer($container);
        $queue->setConnectionName('sync');
        $job = new QueuedEventMessage(new EventMessage(
            MessageId::fromString('018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e'),
            new DateTimeImmutable('2026-08-28 12:34:56.123456+00:00'),
            new QueuedEventMessageIntegrationEvent('event-70', 3),
            Meta::create(['trace_id' => 'trace-70', 'attempt' => 1])
        ));

        $transactions->begin('fight-common', 1);
        /** @phpstan-ignore-next-line Laravel queues accept object jobs despite the inherited contract annotation */
        $queue->push($job);

        self::assertSame([], $calls);

        try {
            $transactions->commit('fight-common', 1, 0);
            self::fail('The first queued attempt must fail after complete fan-out.');
        } catch (RuntimeException $exception) {
            self::assertSame('retry queued event delivery', $exception->getMessage());
        }

        $serializedPayload = $queue->lastPayload();
        $firstAttempt = $queue->lastJob();
        $retryAttempt = new SyncJob($container, $serializedPayload, 'sync', 'default');

        self::assertNotSame($firstAttempt, $retryAttempt);
        self::assertSame($serializedPayload, $retryAttempt->getRawBody());
        $retryAttempt->fire();

        self::assertSame([
            ['event-high', '018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e', '2026-08-28 12:34:56.123456', 'Fight.Test.Common.Adapter.Messaging.Laravel.QueuedEventMessageIntegrationEvent', ['reference' => 'event-70', 'quantity' => 3], ['trace_id' => 'trace-70', 'attempt' => 1]],
            ['event-low', '018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e', '2026-08-28 12:34:56.123456', 'Fight.Test.Common.Adapter.Messaging.Laravel.QueuedEventMessageIntegrationEvent', ['reference' => 'event-70', 'quantity' => 3], ['trace_id' => 'trace-70', 'attempt' => 1]],
            ['all-events', '018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e', '2026-08-28 12:34:56.123456', 'Fight.Test.Common.Adapter.Messaging.Laravel.QueuedEventMessageIntegrationEvent', ['reference' => 'event-70', 'quantity' => 3], ['trace_id' => 'trace-70', 'attempt' => 1]],
            ['event-high', '018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e', '2026-08-28 12:34:56.123456', 'Fight.Test.Common.Adapter.Messaging.Laravel.QueuedEventMessageIntegrationEvent', ['reference' => 'event-70', 'quantity' => 3], ['trace_id' => 'trace-70', 'attempt' => 1]],
            ['event-low', '018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e', '2026-08-28 12:34:56.123456', 'Fight.Test.Common.Adapter.Messaging.Laravel.QueuedEventMessageIntegrationEvent', ['reference' => 'event-70', 'quantity' => 3], ['trace_id' => 'trace-70', 'attempt' => 1]],
            ['all-events', '018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e', '2026-08-28 12:34:56.123456', 'Fight.Test.Common.Adapter.Messaging.Laravel.QueuedEventMessageIntegrationEvent', ['reference' => 'event-70', 'quantity' => 3], ['trace_id' => 'trace-70', 'attempt' => 1]]
        ], $calls);
    }
}

/**
 * Class QueuedEventMessageIntegrationEvent
 */
final readonly class QueuedEventMessageIntegrationEvent implements Event
{
    /**
     * Constructs QueuedEventMessageIntegrationEvent
     */
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

/**
 * Class QueuedEventMessageIntegrationDispatcher
 */
final class QueuedEventMessageIntegrationDispatcher extends SimpleEventDispatcher
{
    private int $attempts = 0;

    public function dispatch(EventMessage $eventMessage): void
    {
        parent::dispatch($eventMessage);

        ++$this->attempts;
        if ($this->attempts === 1) {
            throw new RuntimeException('retry queued event delivery');
        }
    }
}
