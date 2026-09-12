<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Laravel;

use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\Messaging\Laravel\QueuedEventMessage;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueuedEventMessage::class)]
final class QueuedEventMessageTest extends UnitTestCase
{
    public function test_that_handle_reconstitutes_the_complete_event_message(): void
    {
        $message = EventMessage::create(new QueuedEventMessageTestEvent('event-73'));
        $dispatcher = $this->mock(SynchronousEventDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->withArgs(static function (EventMessage $actual) use ($message): bool {
            self::assertSame($message->arraySerialize(), $actual->arraySerialize());

            return true;
        });

        (new QueuedEventMessage($message))->handle(new EventMessageHandler($dispatcher));
    }
}

/**
 * Class QueuedEventMessageTestEvent
 */
final readonly class QueuedEventMessageTestEvent implements Event
{
    /**
     * Constructs QueuedEventMessageTestEvent
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
