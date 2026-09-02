<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Laravel;

use Fight\Common\Adapter\Messaging\Laravel\LaravelEventDispatcher;
use Fight\Common\Adapter\Messaging\Laravel\QueuedEventMessage;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LaravelEventDispatcher::class)]
final class LaravelEventDispatcherTest extends UnitTestCase
{
    public function test_that_trigger_submits_a_queue_job_for_the_created_event_message(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(static fn (mixed $job): bool => $job instanceof QueuedEventMessage);

        (new LaravelEventDispatcher($dispatcher))->trigger(new LaravelEventDispatcherEvent('event-86'));
    }

    public function test_that_dispatch_submits_a_queue_job_for_the_supplied_event_message(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(static fn (mixed $job): bool => $job instanceof QueuedEventMessage);
        $message = EventMessage::create(new LaravelEventDispatcherEvent('message-86'));

        (new LaravelEventDispatcher($dispatcher))->dispatch($message);
    }

    public function test_that_local_subscriber_and_handler_methods_remain_empty_for_async_delivery(): void
    {
        $eventDispatcher = new LaravelEventDispatcher($this->mock(Dispatcher::class));
        $subscriber = $this->mock(EventSubscriber::class);
        $handler = static function (EventMessage $message): void {
        };

        $eventDispatcher->register($subscriber);
        $eventDispatcher->unregister($subscriber);
        $eventDispatcher->addHandler(LaravelEventDispatcherEvent::class, $handler, 10);
        $eventDispatcher->removeHandler(LaravelEventDispatcherEvent::class, $handler);

        self::assertSame([], $eventDispatcher->getHandlers());
        self::assertFalse($eventDispatcher->hasHandlers(LaravelEventDispatcherEvent::class));
    }
}

final readonly class LaravelEventDispatcherEvent implements Event
{
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
