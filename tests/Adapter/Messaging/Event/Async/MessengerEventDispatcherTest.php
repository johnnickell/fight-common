<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Event\Async;

use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Adapter\Messaging\Event\Async\MessengerEventDispatcher;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

#[CoversClass(MessengerEventDispatcher::class)]
class MessengerEventDispatcherTest extends UnitTestCase
{
    public function test_that_trigger_wraps_event_in_message_and_sends(): void
    {
        $event = new SampleEvent();

        $sender = $this->mock(SenderInterface::class);
        $sender->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (Envelope $envelope): Envelope {
                self::assertInstanceOf(EventMessage::class, $envelope->getMessage());

                return $envelope;
            });

        $dispatcher = new MessengerEventDispatcher($sender);

        $dispatcher->trigger($event);
    }

    public function test_that_dispatch_sends_envelope_via_sender(): void
    {
        $eventMessage = EventMessage::create(new SampleEvent());

        $sender = $this->mock(SenderInterface::class);
        $sender->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (Envelope $envelope) use ($eventMessage): Envelope {
                self::assertSame($eventMessage, $envelope->getMessage());

                return $envelope;
            });

        $dispatcher = new MessengerEventDispatcher($sender);

        $dispatcher->dispatch($eventMessage);
    }

    public function test_that_register_is_no_op(): void
    {
        $sender = $this->mock(SenderInterface::class);

        $dispatcher = new MessengerEventDispatcher($sender);

        $dispatcher->register(new SampleEventSubscriber());

        self::assertFalse($dispatcher->hasHandlers());
    }

    public function test_that_unregister_is_no_op(): void
    {
        $sender = $this->mock(SenderInterface::class);

        $dispatcher = new MessengerEventDispatcher($sender);

        $dispatcher->unregister(new SampleEventSubscriber());

        self::assertTrue(true);
    }

    public function test_that_add_handler_is_no_op(): void
    {
        $sender = $this->mock(SenderInterface::class);

        $dispatcher = new MessengerEventDispatcher($sender);

        $dispatcher->addHandler('some_event', function (): void {});

        self::assertTrue(true);
    }

    public function test_that_get_handlers_always_returns_empty_array(): void
    {
        $sender = $this->mock(SenderInterface::class);

        $dispatcher = new MessengerEventDispatcher($sender);

        self::assertSame([], $dispatcher->getHandlers());
        self::assertSame([], $dispatcher->getHandlers('some_event'));
    }

    public function test_that_has_handlers_always_returns_false(): void
    {
        $sender = $this->mock(SenderInterface::class);

        $dispatcher = new MessengerEventDispatcher($sender);

        self::assertFalse($dispatcher->hasHandlers());
        self::assertFalse($dispatcher->hasHandlers('some_event'));
    }

    public function test_that_remove_handler_is_no_op(): void
    {
        $sender = $this->mock(SenderInterface::class);

        $dispatcher = new MessengerEventDispatcher($sender);

        $dispatcher->removeHandler('some_event', function (): void {});

        self::assertTrue(true);
    }
}

class SampleEvent implements Event
{
    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }
}

class SampleEventSubscriber implements EventSubscriber
{
    public static function eventRegistration(): array
    {
        return [];
    }
}
