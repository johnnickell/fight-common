<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Handler;

use Fight\Common\Adapter\Messaging\Handler\SymfonyEventMessageHandler;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SymfonyEventMessageHandler::class)]
class SymfonyEventMessageHandlerTest extends UnitTestCase
{
    public function test_that_invoke_dispatches_event_message(): void
    {
        $eventMessage = EventMessage::create(new SampleEvent());

        $eventDispatcher = $this->mock(SynchronousEventDispatcher::class);
        $eventDispatcher->shouldReceive('dispatch')->once()->with($eventMessage);

        $handler = new SymfonyEventMessageHandler($eventDispatcher);

        $handler->__invoke($eventMessage);
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
