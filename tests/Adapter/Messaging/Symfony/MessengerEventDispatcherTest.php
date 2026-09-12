<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Symfony;

use Fight\Common\Adapter\Messaging\Symfony\MessengerEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

#[CoversClass(MessengerEventDispatcher::class)]
final class MessengerEventDispatcherTest extends UnitTestCase
{
    public function test_that_dispatches_events_and_keeps_local_registration_empty(): void
    {
        $message = EventMessage::create(new MessengerEventDispatcherTestEvent('event-73'));
        $sender = $this->mock(SenderInterface::class);
        $sender->shouldReceive('send')->twice()->andReturnUsing(static function (Envelope $envelope): Envelope {
            self::assertInstanceOf(EventMessage::class, $envelope->getMessage());

            return $envelope;
        });
        $dispatcher = new MessengerEventDispatcher($sender);
        $dispatcher->trigger(new MessengerEventDispatcherTestEvent('trigger-73'));
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
}

/**
 * Class MessengerEventDispatcherTestEvent
 */
final readonly class MessengerEventDispatcherTestEvent implements Event
{
    /**
     * Constructs MessengerEventDispatcherTestEvent
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
