<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\EventSourcing;

use DateTimeImmutable;
use Fight\Common\Application\EventSourcing\EventPublicationFailure;
use Fight\Common\Application\EventSourcing\EventPublicationHandlerFailure;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Event\EventHandlerFailure;
use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use WeakReference;

#[CoversClass(EventPublicationFailure::class)]
#[CoversClass(EventPublicationHandlerFailure::class)]
final class EventPublicationFailureTest extends UnitTestCase
{
    public function test_that_dispatch_failure_conversion_creates_one_bounded_portable_snapshot(): void
    {
        $payload = new FailedPublicationOrderPlaced('order-42');
        $payloadReference = WeakReference::create($payload);
        $message = new EventMessage(
            MessageId::fromString('6ba7b841-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-09T09:14:00.000001+00:00'),
            $payload,
            Meta::create(['secret' => 'must-not-be-retained']),
        );
        $messageReference = WeakReference::create($message);
        $storedEvent = new StoredEvent(
            new StreamId('order', 'order-42'),
            'orders.order-placed',
            2,
            7,
            23,
            $message,
        );
        $storedEventReference = WeakReference::create($storedEvent);
        $firstThrowable = new RuntimeException(
            "Line one\t\n\rLine two\x00\x07\x0B\x7F\xC2\x85\xC3(",
            73,
        );
        $firstThrowableReference = WeakReference::create($firstThrowable);
        $secondThrowable = new LogicException(str_repeat('a', 4092) . "\u{1F642}" . 'tail', 91);
        $secondThrowableReference = WeakReference::create($secondThrowable);
        $dispatchFailure = new EventDispatchFailed([
            new EventHandlerFailure('OrdersSubscriber::onOrderPlaced', $firstThrowable),
            new EventHandlerFailure('Closure (non-replayable)', $secondThrowable),
        ]);

        $failure = EventPublicationFailure::fromDispatchFailure(
            'orders.subscribers',
            $storedEvent,
            new DateTimeImmutable('2026-08-09T04:15:30.123456-05:00'),
            $dispatchFailure,
        );

        self::assertSame('orders.subscribers', $failure->publicationName());
        self::assertSame('order', $failure->streamId()->aggregateName());
        self::assertSame('order-42', $failure->streamId()->identifier());
        self::assertSame('orders.order-placed', $failure->eventName());
        self::assertSame(2, $failure->schemaVersion());
        self::assertSame(7, $failure->streamVersion());
        self::assertSame(23, $failure->globalPosition());
        self::assertSame(
            '6ba7b841-9dad-11d1-80b4-00c04fd430c8',
            $failure->messageId()->toString(),
        );
        self::assertSame(
            '2026-08-09T09:15:30.123456+00:00',
            $failure->dispatchStartedAt()->format('Y-m-d\TH:i:s.uP'),
        );

        $handlerFailures = $failure->handlerFailures();
        self::assertCount(2, $handlerFailures);
        self::assertSame('OrdersSubscriber::onOrderPlaced', $handlerFailures[0]->callableDescription());
        self::assertSame(RuntimeException::class, $handlerFailures[0]->exceptionClass());
        self::assertSame(73, $handlerFailures[0]->exceptionCode());
        self::assertSame("Line one\t\n\rLine two(", $handlerFailures[0]->diagnosticMessage());
        self::assertTrue(mb_check_encoding($handlerFailures[0]->diagnosticMessage(), 'UTF-8'));
        self::assertSame('Closure (non-replayable)', $handlerFailures[1]->callableDescription());
        self::assertSame(LogicException::class, $handlerFailures[1]->exceptionClass());
        self::assertSame(91, $handlerFailures[1]->exceptionCode());
        self::assertSame(4096, strlen($handlerFailures[1]->diagnosticMessage()));
        self::assertStringEndsWith("\u{1F642}", $handlerFailures[1]->diagnosticMessage());
        self::assertTrue(mb_check_encoding($handlerFailures[1]->diagnosticMessage(), 'UTF-8'));

        unset($dispatchFailure, $firstThrowable, $secondThrowable, $storedEvent, $message, $payload);

        self::assertNull($firstThrowableReference->get());
        self::assertNull($secondThrowableReference->get());
        self::assertNull($storedEventReference->get());
        self::assertNull($messageReference->get());
        self::assertNull($payloadReference->get());
    }
}

final readonly class FailedPublicationOrderPlaced implements Event
{
    public function __construct(private string $orderId)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self($data['order_id']);
    }

    public function toArray(): array
    {
        return ['order_id' => $this->orderId];
    }
}
