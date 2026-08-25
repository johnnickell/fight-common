<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Handler;

use DateTimeImmutable;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Domain\Messaging\Event\AllEvents;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventMessageHandler::class)]
final class EventMessageHandlerTest extends UnitTestCase
{
    public function test_that_repeated_delivery_preserves_the_event_occurrence_and_completes_ordered_fan_out(): void
    {
        $message = new EventMessage(
            MessageId::generate(),
            new DateTimeImmutable('2026-08-24 12:34:56.123456+00:00'),
            new EventMessageHandlerSampleEvent('preserved-value'),
            Meta::create(['trace_id' => 'trace-51', 'nested' => ['attempt' => 2]]),
        );
        $calls = [];
        $dispatcher = new SimpleEventDispatcher();
        $dispatcher->addHandler(
            ClassName::underscore(EventMessageHandlerSampleEvent::class),
            static function (EventMessage $received) use (&$calls, $message): void {
                $calls[] = ['event-high', $received];
                self::assertSame($message, $received);
            },
            20,
        );
        $dispatcher->addHandler(
            ClassName::underscore(EventMessageHandlerSampleEvent::class),
            static function (EventMessage $received) use (&$calls): void {
                $calls[] = ['event-low', $received];
            },
            10,
        );
        $dispatcher->addHandler(
            ClassName::underscore(AllEvents::class),
            static function (EventMessage $received) use (&$calls): void {
                $calls[] = ['all-events', $received];
            },
        );

        $handler = new EventMessageHandler($dispatcher);
        $handler($message);
        $handler($message);

        self::assertSame(
            ['event-high', 'event-low', 'all-events', 'event-high', 'event-low', 'all-events'],
            array_column($calls, 0),
        );
        foreach (array_column($calls, 1) as $received) {
            self::assertSame($message, $received);
            self::assertSame($message->id(), $received->id());
            self::assertSame($message->timestamp(), $received->timestamp());
            self::assertSame($message->payload(), $received->payload());
            self::assertSame($message->payloadType()->toString(), $received->payloadType()->toString());
            self::assertSame($message->meta()->toArray(), $received->meta()->toArray());
        }
    }
}

final readonly class EventMessageHandlerSampleEvent implements Event
{
    public function __construct(private string $value)
    {
    }

    public static function fromArray(array $data): static
    {
        return new static($data['value']);
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
