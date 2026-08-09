<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\InMemory;

use DateTimeImmutable;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryPublicationFailureRecorder;
use Fight\Common\Application\EventSourcing\EventPublicationFailure;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Event\EventHandlerFailure;
use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(InMemoryPublicationFailureRecorder::class)]
final class InMemoryPublicationFailureRecorderTest extends UnitTestCase
{
    public function test_that_recording_is_idempotent_by_publication_name_and_global_position(): void
    {
        $recorder = new InMemoryPublicationFailureRecorder();
        $primaryFirst = $this->failure('orders.primary', 1);

        $recorder->record($primaryFirst);
        $recorder->record($primaryFirst);
        $recorder->record($this->failure('orders.primary', 2));
        $recorder->record($this->failure('orders.secondary', 1));

        self::assertSame(
            [
                ['orders.primary', 1],
                ['orders.primary', 2],
                ['orders.secondary', 1],
            ],
            array_map(
                static fn (EventPublicationFailure $failure): array => [
                    $failure->publicationName(),
                    $failure->globalPosition(),
                ],
                $recorder->failures(),
            ),
        );
    }

    private function failure(string $publicationName, int $globalPosition): EventPublicationFailure
    {
        $storedEvent = new StoredEvent(
            new StreamId('order', sprintf('order-%d', $globalPosition)),
            'orders.order-placed',
            1,
            $globalPosition,
            $globalPosition,
            new EventMessage(
                MessageId::fromString('6ba7b841-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-09T09:14:00.000001+00:00'),
                new RecordedPublicationFailedEvent(),
                Meta::create(),
            ),
        );

        return EventPublicationFailure::fromDispatchFailure(
            $publicationName,
            $storedEvent,
            new DateTimeImmutable('2026-08-09T09:15:30.123456+00:00'),
            new EventDispatchFailed([
                new EventHandlerFailure('OrdersSubscriber::onOrderPlaced', new RuntimeException('Failed.', 41)),
            ]),
        );
    }
}

final readonly class RecordedPublicationFailedEvent implements Event
{
    public static function fromArray(array $data): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }
}
