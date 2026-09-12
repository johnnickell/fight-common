<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\CodeIgniter;

use CodeIgniter\Config\Services;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\QueuePushResult;
use DateTimeImmutable;
use Fight\Common\Adapter\Messaging\CodeIgniter\EventMessageJob;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueEventDispatcher;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(EventMessageJob::class)]
#[CoversClass(QueueEventDispatcher::class)]
final class QueueEventDispatcherTest extends UnitTestCase
{
    public function test_that_dispatches_complete_event_messages_and_has_no_local_handlers(): void
    {
        $message = new EventMessage(
            MessageId::fromString('018c0f69-c5a4-7a7d-9bc7-6abddda6cb2e'),
            new DateTimeImmutable('2026-08-29 12:34:56.123456+00:00'),
            new QueueEventDispatcherTestEvent('event-73'),
            Meta::create(['trace_id' => 'trace-73'])
        );
        $queue = $this->mock(QueueInterface::class);
        $queue->shouldReceive('push')->once()->with('events', 'fight-event', [
            'kind'    => 'event',
            'message' => $message->arraySerialize()
        ])->andReturn(QueuePushResult::success(73));
        $dispatcher = new QueueEventDispatcher($queue, 'events', 'fight-event');
        $dispatcher->dispatch($message);

        $subscriber = $this->mock(EventSubscriber::class);
        $handler = static function (): void {
        };
        $dispatcher->register($subscriber);
        $dispatcher->unregister($subscriber);
        $dispatcher->addHandler('event', $handler);
        $dispatcher->removeHandler('event', $handler);
        self::assertSame([], $dispatcher->getHandlers());
        self::assertFalse($dispatcher->hasHandlers());
    }

    public function test_that_rejects_queue_failures_and_invalid_job_payloads(): void
    {
        $queue = $this->mock(QueueInterface::class);
        $queue->shouldReceive('push')->once()->andReturn(QueuePushResult::failure());
        try {
            (new QueueEventDispatcher($queue, 'events', 'fight-event'))->trigger(new QueueEventDispatcherTestEvent('event-73'));
            self::fail('Expected the queue failure to be visible.');
        } catch (RuntimeException $error) {
            self::assertSame('CodeIgniter Queue could not submit event message: unknown queue failure', $error->getMessage());
        }

        foreach (
            [
            [['kind' => 'command', 'message' => []], 'Queued payload kind must be event.'],
            [['kind' => 'event', 'message' => 'invalid'], 'Queued payload message must be an array.']
            ] as [$data, $message]
        ) {
            try {
                (new EventMessageJob($data))->process();
                self::fail('Expected an invalid payload to be rejected.');
            } catch (InvalidArgumentException $error) {
                self::assertSame($message, $error->getMessage());
            }
        }
    }

    public function test_that_job_hands_reconstituted_messages_to_its_service_and_rejects_invalid_services(): void
    {
        $message = EventMessage::create(new QueueEventDispatcherTestEvent('event-73'));
        $synchronous = $this->mock(SynchronousEventDispatcher::class);
        $synchronous->shouldReceive('dispatch')->once()->withArgs(static function (EventMessage $actual) use ($message): bool {
            self::assertSame($message->arraySerialize(), $actual->arraySerialize());

            return true;
        });
        Services::override(EventMessageJob::HANDLER_SERVICE, new EventMessageHandler($synchronous));
        try {
            (new EventMessageJob([
                'kind'    => 'event',
                'message' => $message->arraySerialize()
            ]))->process();
        } finally {
            Services::reset(false);
        }

        Services::override(EventMessageJob::HANDLER_SERVICE, new \stdClass());
        try {
            (new EventMessageJob(['kind' => 'event', 'message' => []]))->process();
            self::fail('Expected an invalid handler service to be rejected.');
        } catch (RuntimeException $error) {
            self::assertSame(
                'CodeIgniter service "fightEventMessageHandler" must resolve to '.EventMessageHandler::class.'.',
                $error->getMessage()
            );
        } finally {
            Services::reset(false);
        }
    }
}

/**
 * Class QueueEventDispatcherTestEvent
 */
final readonly class QueueEventDispatcherTestEvent implements Event
{
    /**
     * Constructs QueueEventDispatcherTestEvent
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
