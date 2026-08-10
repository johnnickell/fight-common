<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use DateTimeImmutable;
use Fight\Common\Application\EventSourcing\EventPublicationFailure;
use Fight\Common\Application\EventSourcing\PublicationFailureRecorder;
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
use RuntimeException;

/**
 * Class PublicationFailureRecorderConformanceTestCase
 *
 * Reusable behavioral contract for publication failure recorders
 */
abstract class PublicationFailureRecorderConformanceTestCase extends UnitTestCase
{
    /**
     * Verifies correlation-key independence and preservation of first evidence
     */
    public function test_that_record_is_idempotent_by_publication_name_and_global_position(): void
    {
        $recorder = $this->createPublicationFailureRecorder();

        $recorder->record($this->failure());
        $recorder->record($this->failure(aggregateIdentifier: 'replacement-order'));
        $recorder->record($this->failure(publicationName: 'orders.secondary'));
        $recorder->record($this->failure(globalPosition: 24, aggregateIdentifier: 'order-24'));

        self::assertSame(
            [
                ['orders.secondary', 23, 'order-42'],
                ['orders.subscribers', 23, 'order-42'],
                ['orders.subscribers', 24, 'order-24'],
            ],
            $this->recordedFailureCorrelations($recorder),
        );
    }

    /**
     * Creates the publication failure recorder under test
     */
    abstract protected function createPublicationFailureRecorder(): PublicationFailureRecorder;

    /**
     * Returns recorded correlation keys and their first aggregate evidence
     *
     * @return list<array{string, int, string}>
     */
    abstract protected function recordedFailureCorrelations(
        PublicationFailureRecorder $recorder,
    ): array;

    /**
     * Creates the expected portable failure fixture
     */
    protected function failure(
        string $publicationName = 'orders.subscribers',
        int $globalPosition = 23,
        string $aggregateIdentifier = 'order-42',
    ): EventPublicationFailure {
        $storedEvent = new StoredEvent(
            new StreamId('order', $aggregateIdentifier),
            'orders.order-placed',
            2,
            7,
            $globalPosition,
            new EventMessage(
                MessageId::fromString('6ba7b841-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-09T09:14:00.000001+00:00'),
                new PublicationFailedEventFixture(),
                Meta::create(),
            ),
        );

        return EventPublicationFailure::fromDispatchFailure(
            $publicationName,
            $storedEvent,
            new DateTimeImmutable('2026-08-09T04:15:30.123456-05:00'),
            new EventDispatchFailed([
                new EventHandlerFailure(
                    'OrdersSubscriber::onOrderPlaced',
                    new RuntimeException('Inventory unavailable.', 73),
                ),
                new EventHandlerFailure(
                    'AuditSubscriber::__invoke',
                    new LogicException('Audit unavailable.', 91),
                ),
            ]),
        );
    }
}

final readonly class PublicationFailedEventFixture implements Event
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
