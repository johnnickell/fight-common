<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\CodeIgniter;

use CodeIgniter\Config\Services;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\QueuePushResult;
use Fight\Common\Adapter\Messaging\CodeIgniter\EventMessageJob;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueEventDispatcher;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\UnitTestCase;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RuntimeException;
use Throwable;

#[CoversClass(QueueEventDispatcher::class)]
#[CoversClass(EventMessageJob::class)]
final class QueueEventDispatcherIntegrationTest extends UnitTestCase
{
    public function test_that_event_producer_contract_and_job_failures_remain_visible(): void
    {
        $queue = $this->mock(QueueInterface::class);
        $queue->shouldReceive('push')->once()->andReturn(QueuePushResult::success(73));
        $dispatcher = new QueueEventDispatcher($queue, 'events', 'fight-event');
        $dispatcher->trigger(new QueueEventDispatcherIntegrationEvent('event-73', 3));

        $subscriber = new class implements EventSubscriber {
            public static function eventRegistration(): array
            {
                return [];
            }
        };
        $handler = static function (): void {
        };
        $dispatcher->register($subscriber);
        $dispatcher->unregister($subscriber);
        $dispatcher->addHandler('event', $handler);
        self::assertSame([], $dispatcher->getHandlers());
        self::assertFalse($dispatcher->hasHandlers());
        $dispatcher->removeHandler('event', $handler);

        $failedQueue = $this->mock(QueueInterface::class);
        $failedQueue->shouldReceive('push')->once()->andReturn(QueuePushResult::failure());

        try {
            (new QueueEventDispatcher($failedQueue, 'events', 'fight-event'))->trigger(
                new QueueEventDispatcherIntegrationEvent('event-73', 3)
            );
            self::fail('Expected the Queue push failure to escape.');
        } catch (RuntimeException $failure) {
            self::assertSame(
                'CodeIgniter Queue could not submit event message: unknown queue failure',
                $failure->getMessage()
            );
        }

        foreach (
            [
                ['data' => ['kind' => 'command', 'message' => []], 'message' => 'Queued payload kind must be event.'],
                ['data' => ['kind' => 'event', 'message' => 'invalid'], 'message' => 'Queued payload message must be an array.']
            ] as $case
        ) {
            try {
                (new EventMessageJob($case['data']))->process();
                self::fail('Expected the invalid event job payload to be rejected.');
            } catch (InvalidArgumentException $failure) {
                self::assertSame($case['message'], $failure->getMessage());
            }
        }

        Services::override(EventMessageJob::HANDLER_SERVICE, new \stdClass());

        try {
            (new EventMessageJob(['kind' => 'event', 'message' => []]))->process();
            self::fail('Expected an invalid event handler service to be rejected.');
        } catch (RuntimeException $failure) {
            self::assertSame(
                'CodeIgniter service "fightEventMessageHandler" must resolve to '.EventMessageHandler::class.'.',
                $failure->getMessage()
            );
        } finally {
            Services::reset(false);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_database_queue_retries_the_exact_event_message_through_complete_ordered_fan_out(): void
    {
        $fixture = DatabaseQueueFixture::boot([
            'fight-event' => EventMessageJob::class,
        ]);

        $calls = [];
        $failFirstAttempt = true;
        $fanOutFailure = new RuntimeException('first fan-out attempt fails after delivery');
        $dispatcher = new SimpleEventDispatcher();
        $dispatcher->addHandler(ClassName::underscore(QueueEventDispatcherIntegrationEvent::class), static function (EventMessage $message) use (&$calls): void {
            $calls[] = ['high', $message->arraySerialize()];
        }, 100);
        $dispatcher->addHandler(ClassName::underscore(QueueEventDispatcherIntegrationEvent::class), static function (EventMessage $message) use (&$calls, &$failFirstAttempt, $fanOutFailure): void {
            $calls[] = ['low', $message->arraySerialize()];
            if ($failFirstAttempt) {
                $failFirstAttempt = false;

                throw $fanOutFailure;
            }
        });
        $dispatcher->addHandler(ClassName::underscore(\Fight\Common\Domain\Messaging\Event\AllEvents::class), static function (EventMessage $message) use (&$calls): void {
            $calls[] = ['all', $message->arraySerialize()];
        });
        Services::override(EventMessageJob::HANDLER_SERVICE, new EventMessageHandler($dispatcher));

        try {
            $message = new EventMessage(
                MessageId::fromString('018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e'),
                new DateTimeImmutable('2026-08-29 12:34:56.123456+00:00'),
                new QueueEventDispatcherIntegrationEvent('event-73', 3),
                Meta::create(['trace_id' => 'trace-73', 'attempt' => 1]),
            );

            (new QueueEventDispatcher($fixture->queue, 'events', 'fight-event'))->dispatch($message);
            $first = $fixture->queue->pop('events', ['default']);
            self::assertNotNull($first);
            self::assertSame('fight-event', $first->payload['job']);
            self::assertSame('event', $first->payload['data']['kind']);
            self::assertSame($message->arraySerialize(), $first->payload['data']['message']);

            try {
                (new EventMessageJob($first->payload['data']))->process();
                self::fail('Expected the first synchronous fan-out attempt to escape from the job.');
            } catch (Throwable $failure) {
                self::assertInstanceOf(EventDispatchFailed::class, $failure);
                self::assertTrue($fixture->queue->failed($first, $failure, true));
            }

            $failedJobs = $fixture->queue->listFailed('events');
            self::assertCount(1, $failedJobs);
            self::assertSame('fight-event', $failedJobs[0]->payload['job']);
            self::assertSame($message->arraySerialize(), $failedJobs[0]->payload['data']['message']);
            self::assertSame(1, $fixture->queue->retry(null, 'events'));

            $retry = $fixture->queue->pop('events', ['default']);
            self::assertNotNull($retry);
            self::assertSame('fight-event', $retry->payload['job']);
            self::assertSame($message->arraySerialize(), $retry->payload['data']['message']);
            (new EventMessageJob($retry->payload['data']))->process();
            self::assertTrue($fixture->queue->done($retry));

            self::assertSame([], $fixture->queue->listFailed('events'));
            self::assertSame([
                ['high', $message->arraySerialize()], ['low', $message->arraySerialize()], ['all', $message->arraySerialize()],
                ['high', $message->arraySerialize()], ['low', $message->arraySerialize()], ['all', $message->arraySerialize()],
            ], $calls);
        } finally {
            Services::reset(false);
            $fixture->close();
        }
    }
}

final readonly class QueueEventDispatcherIntegrationEvent implements Event
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
