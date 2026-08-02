<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\EventSourcing;

use DateTimeImmutable;
use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(StoredEvent::class)]
class StoredEventTest extends UnitTestCase
{
    public function test_that_it_exposes_the_stored_identity_positions_and_current_message(): void
    {
        $streamId = new StreamId('orders', 'order-42');
        $message = new EventMessage(
            MessageId::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T09:15:30.123456+00:00'),
            new StoredOccurrenceTestEvent('placed'),
            Meta::create(['trace_id' => 'trace-7']),
        );

        $storedEvent = new StoredEvent(
            $streamId,
            'orders.order-placed',
            2,
            3,
            17,
            $message,
        );

        self::assertSame($streamId, $storedEvent->streamId());
        self::assertSame('orders.order-placed', $storedEvent->eventName());
        self::assertSame(2, $storedEvent->schemaVersion());
        self::assertSame(3, $storedEvent->streamVersion());
        self::assertSame(17, $storedEvent->globalPosition());
        self::assertSame($message, $storedEvent->message());
    }

    #[DataProvider('invalid_stored_identity_provider')]
    public function test_that_it_rejects_an_empty_event_name_or_a_position_below_one(
        string $eventName,
        int $schemaVersion,
        int $streamVersion,
        int $globalPosition,
    ): void {
        $this->expectException(DomainException::class);

        new StoredEvent(
            new StreamId('orders', 'order-42'),
            $eventName,
            $schemaVersion,
            $streamVersion,
            $globalPosition,
            new EventMessage(
                MessageId::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T09:15:30.123456+00:00'),
                new StoredOccurrenceTestEvent('placed'),
                Meta::create(),
            ),
        );
    }

    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function invalid_stored_identity_provider(): iterable
    {
        yield 'empty event name' => ['', 1, 1, 1];
        yield 'schema version zero' => ['orders.order-placed', 0, 1, 1];
        yield 'stream version zero' => ['orders.order-placed', 1, 0, 1];
        yield 'global position zero' => ['orders.order-placed', 1, 1, 0];
    }
}

final readonly class StoredOccurrenceTestEvent implements Event
{
    public function __construct(public string $status)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self($data['status']);
    }

    public function toArray(): array
    {
        return ['status' => $this->status];
    }
}
