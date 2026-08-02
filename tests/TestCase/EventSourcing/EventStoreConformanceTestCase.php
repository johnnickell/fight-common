<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use DateTimeImmutable;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventMapping;
use Fight\Common\Domain\EventSourcing\EventMappingProvider;
use Fight\Common\Domain\EventSourcing\EventStore;
use Fight\Common\Domain\EventSourcing\Exception\EventMappingException;
use Fight\Common\Domain\EventSourcing\Exception\OptimisticConcurrencyException;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\EventSourcing\Upcaster;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;

abstract class EventStoreConformanceTestCase extends UnitTestCase
{
    abstract protected function createEventStore(EventMapper $eventMapper): EventStore;

    /**
     * @param list<ConformanceHistoricalEventRecord> $historicalRecords
     */
    abstract protected function createEventStoreWithHistory(
        EventMapper $eventMapper,
        array $historicalRecords,
    ): EventStore;

    public function test_that_historical_reads_upcast_in_order_while_preserving_stored_provenance(): void
    {
        $eventMapper = new EventMapper([new ConformanceHistoricalEventMappingProvider()]);
        $firstStream = new StreamId('order', 'order-42');
        $secondStream = new StreamId('order', 'order-43');
        $eventStore = $this->createEventStoreWithHistory($eventMapper, [
            new ConformanceHistoricalEventRecord(
                $firstStream,
                'orders.order-placed',
                1,
                1,
                1,
                ['legacy_order_id' => 'order-42-first'],
                MessageId::fromString('6ba7b830-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T04:15:30.123456-05:00'),
                ['trace' => ['id' => 'historical-1']],
            ),
            new ConformanceHistoricalEventRecord(
                $secondStream,
                'orders.order-placed',
                2,
                1,
                2,
                ['order_id' => 'order-43'],
                MessageId::fromString('6ba7b831-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T09:16:30.234567+00:00'),
                ['trace' => ['id' => 'historical-2']],
            ),
            new ConformanceHistoricalEventRecord(
                $firstStream,
                'orders.order-placed',
                2,
                2,
                3,
                ['order_id' => 'order-42-second'],
                MessageId::fromString('6ba7b832-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T09:17:30.345678+00:00'),
                ['trace' => ['id' => 'historical-3']],
            ),
        ]);

        $streamEvents = [...$eventStore->readStream($firstStream)];
        $globalEvents = [...$eventStore->readAllAfter(0, 10)];
        $streamEvents[0]->message()->meta()->set('trace', ['id' => 'changed-after-read']);
        $rereadFirstEvent = [...$eventStore->readStream($firstStream)][0];

        self::assertSame([1, 2], array_map(
            static fn ($event): int => $event->schemaVersion(),
            $streamEvents,
        ));
        self::assertSame(['order-42-first', 'order-42-second'], array_map(
            static fn ($event): string => $event->message()->payload()->toArray()['order_id'],
            $streamEvents,
        ));
        self::assertSame([1, 3], array_map(
            static fn ($event): int => $event->globalPosition(),
            $streamEvents,
        ));
        self::assertSame(['order-42-first', 'order-43', 'order-42-second'], array_map(
            static fn ($event): string => $event->message()->payload()->toArray()['order_id'],
            $globalEvents,
        ));
        self::assertSame(
            '2026-08-02T09:15:30.123456+00:00',
            $streamEvents[0]->message()->timestamp()->format('Y-m-d\TH:i:s.uP'),
        );
        self::assertSame(
            ['trace' => ['id' => 'historical-1']],
            $rereadFirstEvent->message()->meta()->toArray(),
        );
    }

    public function test_that_global_polling_is_strictly_after_bounded_and_prefix_stable(): void
    {
        $eventMapper = new EventMapper([new ConformanceEventMappingProvider()]);
        $firstStream = new StreamId('order', 'order-42');
        $secondStream = new StreamId('order', 'order-43');
        $eventStore = $this->createEventStoreWithHistory($eventMapper, [
            new ConformanceHistoricalEventRecord(
                $firstStream,
                'orders.order-placed',
                1,
                1,
                1,
                ['order_id' => 'order-42-first'],
                MessageId::fromString('6ba7b833-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T09:18:30.456789+00:00'),
                [],
            ),
            new ConformanceHistoricalEventRecord(
                $secondStream,
                'orders.order-placed',
                1,
                1,
                2,
                ['order_id' => 'order-43-first'],
                MessageId::fromString('6ba7b834-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T09:19:30.567890+00:00'),
                [],
            ),
        ]);

        $visiblePrefix = [...$eventStore->readAllAfter(0, 2)];
        $eventStore->append($firstStream, 1, [new EventMessage(
            MessageId::fromString('6ba7b835-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T09:20:30.678901+00:00'),
            new ConformanceOrderPlaced('order-42-second'),
            Meta::create(),
        )]);

        self::assertSame([1, 2], array_map(
            static fn ($event): int => $event->globalPosition(),
            $visiblePrefix,
        ));
        self::assertSame([2], array_map(
            static fn ($event): int => $event->globalPosition(),
            [...$eventStore->readAllAfter(1, 1)],
        ));
        self::assertSame([3], array_map(
            static fn ($event): int => $event->globalPosition(),
            [...$eventStore->readAllAfter(2, 1)],
        ));
        self::assertSame([1, 2], array_map(
            static fn ($event): int => $event->globalPosition(),
            [...$eventStore->readAllAfter(0, 2)],
        ));
    }

    public function test_that_historical_reads_fail_closed_for_unknown_aliases_and_future_schemas(): void
    {
        $eventMapper = new EventMapper([new ConformanceEventMappingProvider()]);
        $streamId = new StreamId('order', 'order-42');
        $unknownAliasStore = $this->createEventStoreWithHistory($eventMapper, [
            new ConformanceHistoricalEventRecord(
                $streamId,
                'orders.unknown-event',
                1,
                1,
                1,
                ['order_id' => 'order-42'],
                MessageId::fromString('6ba7b836-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T09:21:30.789012+00:00'),
                [],
            ),
            new ConformanceHistoricalEventRecord(
                $streamId,
                'orders.order-placed',
                1,
                2,
                2,
                ['order_id' => 'must-not-be-skipped-to'],
                MessageId::fromString('6ba7b837-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T09:22:30.890123+00:00'),
                [],
            ),
        ]);

        try {
            [...$unknownAliasStore->readStream($streamId)];
            self::fail('An unknown historical event alias must fail closed.');
        } catch (EventMappingException $exception) {
            self::assertSame('Unknown event alias: orders.unknown-event.', $exception->getMessage());
        }

        $futureSchemaStore = $this->createEventStoreWithHistory($eventMapper, [
            new ConformanceHistoricalEventRecord(
                $streamId,
                'orders.order-placed',
                2,
                1,
                1,
                ['order_id' => 'order-42'],
                MessageId::fromString('6ba7b838-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T09:23:30.901234+00:00'),
                [],
            ),
            new ConformanceHistoricalEventRecord(
                $streamId,
                'orders.order-placed',
                1,
                2,
                2,
                ['order_id' => 'must-not-be-skipped-to'],
                MessageId::fromString('6ba7b839-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T09:24:30.012345+00:00'),
                [],
            ),
        ]);

        try {
            [...$futureSchemaStore->readAllAfter(0, 10)];
            self::fail('A future historical event schema must fail closed.');
        } catch (EventMappingException $exception) {
            self::assertSame(
                'Unsupported schema version 2 for event orders.order-placed.',
                $exception->getMessage(),
            );
        }
    }

    public function test_that_append_maps_the_whole_batch_before_writing_any_event(): void
    {
        $eventStore = $this->createEventStore(new EventMapper([new ConformanceEventMappingProvider()]));
        $streamId = new StreamId('order', 'order-42');

        try {
            $eventStore->append($streamId, 0, [
                new EventMessage(
                    MessageId::fromString('6ba7b828-9dad-11d1-80b4-00c04fd430c8'),
                    new DateTimeImmutable('2026-08-02T10:09:00.000012+00:00'),
                    new ConformanceOrderPlaced('order-42'),
                    Meta::create(),
                ),
                new EventMessage(
                    MessageId::fromString('6ba7b829-9dad-11d1-80b4-00c04fd430c8'),
                    new DateTimeImmutable('2026-08-02T10:10:00.000013+00:00'),
                    new ConformanceUnmappedEvent('unmapped'),
                    Meta::create(),
                ),
            ]);
            self::fail('A mapping failure must propagate.');
        } catch (EventMappingException $exception) {
            self::assertSame(
                'Unknown event class: Fight\\Test\\Common\\TestCase\\EventSourcing\\ConformanceUnmappedEvent.',
                $exception->getMessage(),
            );
        }

        self::assertSame([], [...$eventStore->readStream($streamId)]);
        self::assertSame([], [...$eventStore->readAllAfter(0, 10)]);
    }

    public function test_that_append_rejects_partial_reordered_and_misplaced_retries_without_writing(): void
    {
        $eventStore = $this->createEventStore(new EventMapper([new ConformanceEventMappingProvider()]));
        $streamId = new StreamId('order', 'order-42');
        $firstMessage = new EventMessage(
            MessageId::fromString('6ba7b825-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T10:06:00.000009+00:00'),
            new ConformanceOrderPlaced('order-42-first'),
            Meta::create(),
        );
        $secondMessage = new EventMessage(
            MessageId::fromString('6ba7b826-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T10:07:00.000010+00:00'),
            new ConformanceOrderPlaced('order-42-second'),
            Meta::create(),
        );
        $newMessage = new EventMessage(
            MessageId::fromString('6ba7b827-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T10:08:00.000011+00:00'),
            new ConformanceOrderPlaced('order-42-new'),
            Meta::create(),
        );
        $eventStore->append($streamId, 0, [$firstMessage, $secondMessage]);

        foreach ([
            'partial' => [$firstMessage, $newMessage],
            'reordered' => [$secondMessage, $firstMessage],
            'misplaced' => [$secondMessage],
        ] as $failure => $messages) {
            try {
                $eventStore->append($streamId, 0, $messages);
                self::fail(sprintf('A %s retry must fail.', $failure));
            } catch (OptimisticConcurrencyException $exception) {
                self::assertSame($streamId, $exception->streamId());
                self::assertSame(0, $exception->expectedVersion());
                self::assertSame(2, $exception->actualVersion());
            }
        }

        $storedEvents = [...$eventStore->readStream($streamId)];

        self::assertCount(2, $storedEvents);
        self::assertSame([
            '6ba7b825-9dad-11d1-80b4-00c04fd430c8',
            '6ba7b826-9dad-11d1-80b4-00c04fd430c8',
        ], array_map(
            static fn ($event): string => (string) $event->message()->id(),
            $storedEvents,
        ));
    }

    public function test_that_append_rejects_a_message_id_owned_by_another_stream_without_changing_either_stream(): void
    {
        $eventStore = $this->createEventStore(new EventMapper([new ConformanceEventMappingProvider()]));
        $sourceStream = new StreamId('order', 'order-42');
        $targetStream = new StreamId('order', 'order-43');
        $messageId = MessageId::fromString('6ba7b824-9dad-11d1-80b4-00c04fd430c8');
        $eventStore->append($sourceStream, 0, [new EventMessage(
            $messageId,
            new DateTimeImmutable('2026-08-02T10:04:00.000007+00:00'),
            new ConformanceOrderPlaced('order-42'),
            Meta::create(),
        )]);

        try {
            $eventStore->append($targetStream, 0, [new EventMessage(
                $messageId,
                new DateTimeImmutable('2026-08-02T10:05:00.000008+00:00'),
                new ConformanceOrderPlaced('order-43'),
                Meta::create(),
            )]);
            self::fail('A message ID owned by another stream must fail.');
        } catch (OptimisticConcurrencyException $exception) {
            self::assertSame($targetStream, $exception->streamId());
            self::assertSame(0, $exception->expectedVersion());
            self::assertSame(0, $exception->actualVersion());
        }

        self::assertCount(1, [...$eventStore->readStream($sourceStream)]);
        self::assertSame([], [...$eventStore->readStream($targetStream)]);
        self::assertCount(1, [...$eventStore->readAllAfter(0, 10)]);
    }

    public function test_that_append_rejects_duplicate_message_ids_in_a_new_batch_without_writing(): void
    {
        $eventStore = $this->createEventStore(new EventMapper([new ConformanceEventMappingProvider()]));
        $streamId = new StreamId('order', 'order-42');
        $messageId = MessageId::fromString('6ba7b83a-9dad-11d1-80b4-00c04fd430c8');

        try {
            $eventStore->append($streamId, 0, [
                new EventMessage(
                    $messageId,
                    new DateTimeImmutable('2026-08-02T10:06:00.000009+00:00'),
                    new ConformanceOrderPlaced('order-42-first'),
                    Meta::create(),
                ),
                new EventMessage(
                    $messageId,
                    new DateTimeImmutable('2026-08-02T10:07:00.000010+00:00'),
                    new ConformanceOrderPlaced('order-42-second'),
                    Meta::create(),
                ),
            ]);
            self::fail('Duplicate message IDs within a new batch must fail.');
        } catch (OptimisticConcurrencyException $exception) {
            self::assertSame($streamId, $exception->streamId());
            self::assertSame(0, $exception->expectedVersion());
            self::assertSame(0, $exception->actualVersion());
        }

        self::assertSame([], [...$eventStore->readStream($streamId)]);
        self::assertSame([], [...$eventStore->readAllAfter(0, 10)]);
    }

    public function test_that_append_accepts_an_exact_message_id_retry_without_writing_again(): void
    {
        $eventStore = $this->createEventStore(new EventMapper([new ConformanceEventMappingProvider()]));
        $streamId = new StreamId('order', 'order-42');
        $firstId = MessageId::fromString('6ba7b822-9dad-11d1-80b4-00c04fd430c8');
        $secondId = MessageId::fromString('6ba7b823-9dad-11d1-80b4-00c04fd430c8');
        $eventStore->append($streamId, 0, [
            new EventMessage(
                $firstId,
                new DateTimeImmutable('2026-08-02T10:02:00.000003+00:00'),
                new ConformanceOrderPlaced('original-first'),
                Meta::create(['attempt' => 'original']),
            ),
            new EventMessage(
                $secondId,
                new DateTimeImmutable('2026-08-02T10:03:00.000004+00:00'),
                new ConformanceOrderPlaced('original-second'),
                Meta::create(['attempt' => 'original']),
            ),
        ]);

        $eventStore->append($streamId, 0, [
            new EventMessage(
                $firstId,
                new DateTimeImmutable('2026-08-02T11:02:00.000005+00:00'),
                new ConformanceOrderPlaced('replacement-first'),
                Meta::create(['attempt' => 'retry']),
            ),
            new EventMessage(
                $secondId,
                new DateTimeImmutable('2026-08-02T11:03:00.000006+00:00'),
                new ConformanceOrderPlaced('replacement-second'),
                Meta::create(['attempt' => 'retry']),
            ),
        ]);

        $storedEvents = [...$eventStore->readStream($streamId)];

        self::assertCount(2, $storedEvents);
        self::assertSame([1, 2], array_map(
            static fn ($event): int => $event->streamVersion(),
            $storedEvents,
        ));
        self::assertSame(['original-first', 'original-second'], array_map(
            static fn ($event): string => $event->message()->payload()->toArray()['order_id'],
            $storedEvents,
        ));
        self::assertSame([1, 2], array_map(
            static fn ($event): int => $event->globalPosition(),
            [...$eventStore->readAllAfter(0, 10)],
        ));
    }

    public function test_that_append_rejects_a_stale_expected_version_without_changing_the_stream(): void
    {
        $eventStore = $this->createEventStore(new EventMapper([new ConformanceEventMappingProvider()]));
        $streamId = new StreamId('order', 'order-42');
        $eventStore->append($streamId, 0, [new EventMessage(
            MessageId::fromString('6ba7b820-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T10:00:00.000001+00:00'),
            new ConformanceOrderPlaced('order-42'),
            Meta::create(),
        )]);

        try {
            $eventStore->append($streamId, 0, [new EventMessage(
                MessageId::fromString('6ba7b821-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T10:01:00.000002+00:00'),
                new ConformanceOrderPlaced('order-42'),
                Meta::create(),
            )]);
            self::fail('A stale expected version must fail.');
        } catch (OptimisticConcurrencyException $exception) {
            self::assertSame($streamId, $exception->streamId());
            self::assertSame(0, $exception->expectedVersion());
            self::assertSame(1, $exception->actualVersion());
        }

        $storedEvents = [...$eventStore->readStream($streamId)];

        self::assertCount(1, $storedEvents);
        self::assertSame('6ba7b820-9dad-11d1-80b4-00c04fd430c8', (string) $storedEvents[0]->message()->id());
    }

    public function test_that_stream_identity_includes_aggregate_name_and_identifier(): void
    {
        $eventStore = $this->createEventStore(new EventMapper([new ConformanceEventMappingProvider()]));
        $orderStream = new StreamId('order', 'shared-42');
        $invoiceStream = new StreamId('invoice', 'shared-42');
        $eventStore->append($orderStream, 0, [new EventMessage(
            MessageId::fromString('6ba7b83b-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T10:11:00.000014+00:00'),
            new ConformanceOrderPlaced('order-payload'),
            Meta::create(),
        )]);
        $eventStore->append($invoiceStream, 0, [new EventMessage(
            MessageId::fromString('6ba7b83c-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T10:12:00.000015+00:00'),
            new ConformanceOrderPlaced('invoice-payload'),
            Meta::create(),
        )]);

        $orderEvents = [...$eventStore->readStream($orderStream)];
        $invoiceEvents = [...$eventStore->readStream($invoiceStream)];
        $globalEvents = [...$eventStore->readAllAfter(0, 10)];

        self::assertSame([1], array_map(
            static fn ($event): int => $event->streamVersion(),
            $orderEvents,
        ));
        self::assertSame(['order-payload'], array_map(
            static fn ($event): string => $event->message()->payload()->toArray()['order_id'],
            $orderEvents,
        ));
        self::assertSame([1], array_map(
            static fn ($event): int => $event->streamVersion(),
            $invoiceEvents,
        ));
        self::assertSame(['invoice-payload'], array_map(
            static fn ($event): string => $event->message()->payload()->toArray()['order_id'],
            $invoiceEvents,
        ));
        self::assertSame(['order-payload', 'invoice-payload'], array_map(
            static fn ($event): string => $event->message()->payload()->toArray()['order_id'],
            $globalEvents,
        ));
        self::assertSame([1, 2], array_map(
            static fn ($event): int => $event->globalPosition(),
            $globalEvents,
        ));
    }

    public function test_that_append_maps_snapshots_and_returns_events_in_stream_and_global_order(): void
    {
        $eventMapper = new EventMapper([new ConformanceEventMappingProvider()]);
        $eventStore = $this->createEventStore($eventMapper);
        $firstStream = new StreamId('order', 'order-42');
        $secondStream = new StreamId('order', 'order-43');
        $firstMeta = Meta::create(['trace_id' => 'trace-1']);
        $firstMessage = new EventMessage(
            MessageId::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T09:15:30.123456+00:00'),
            new ConformanceOrderPlaced('order-42'),
            $firstMeta,
        );
        $secondMessage = new EventMessage(
            MessageId::fromString('6ba7b811-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T09:16:30.234567+00:00'),
            new ConformanceOrderPlaced('order-42'),
            Meta::create(['trace_id' => 'trace-2']),
        );
        $thirdMessage = new EventMessage(
            MessageId::fromString('6ba7b812-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T09:17:30.345678+00:00'),
            new ConformanceOrderPlaced('order-43'),
            Meta::create(['trace_id' => 'trace-3']),
        );

        $eventStore->append($firstStream, 0, [$firstMessage, $secondMessage]);
        $firstMeta->set('trace_id', 'changed-after-append');
        $eventStore->append($secondStream, 0, [$thirdMessage]);

        $streamEvents = [...$eventStore->readStream($firstStream)];
        $globalEvents = [...$eventStore->readAllAfter(0, 10)];

        self::assertCount(2, $streamEvents);
        self::assertSame([1, 2], array_map(
            static fn ($event): int => $event->streamVersion(),
            $streamEvents,
        ));
        self::assertSame(['orders.order-placed', 'orders.order-placed'], array_map(
            static fn ($event): string => $event->eventName(),
            $streamEvents,
        ));
        self::assertSame([1, 1], array_map(
            static fn ($event): int => $event->schemaVersion(),
            $streamEvents,
        ));
        self::assertSame(['order-42', 'order-42'], array_map(
            static fn ($event): string => $event->message()->payload()->toArray()['order_id'],
            $streamEvents,
        ));
        self::assertSame(['trace_id' => 'trace-1'], $streamEvents[0]->message()->meta()->toArray());
        self::assertSame(
            '2026-08-02T09:15:30.123456+00:00',
            $streamEvents[0]->message()->timestamp()->format('Y-m-d\TH:i:s.uP'),
        );
        self::assertSame([1, 2, 3], array_map(
            static fn ($event): int => $event->globalPosition(),
            $globalEvents,
        ));
        self::assertSame(['order-42', 'order-42', 'order-43'], array_map(
            static fn ($event): string => $event->message()->payload()->toArray()['order_id'],
            $globalEvents,
        ));
    }
}

final readonly class ConformanceEventMappingProvider implements EventMappingProvider
{
    public function namespace(): string
    {
        return 'orders';
    }

    public function mappings(): iterable
    {
        yield new EventMapping('order-placed', ConformanceOrderPlaced::class, 1);
    }
}

final readonly class ConformanceHistoricalEventMappingProvider implements EventMappingProvider
{
    public function namespace(): string
    {
        return 'orders';
    }

    public function mappings(): iterable
    {
        yield new EventMapping(
            'order-placed',
            ConformanceOrderPlaced::class,
            2,
            [new ConformanceOrderPlacedUpcaster()],
        );
    }
}

final readonly class ConformanceOrderPlacedUpcaster implements Upcaster
{
    public function sourceSchemaVersion(): int
    {
        return 1;
    }

    public function targetSchemaVersion(): int
    {
        return 2;
    }

    public function upcast(array $data): array
    {
        return ['order_id' => $data['legacy_order_id']];
    }
}

final readonly class ConformanceHistoricalEventRecord
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public StreamId $streamId,
        public string $eventName,
        public int $schemaVersion,
        public int $streamVersion,
        public int $globalPosition,
        public array $data,
        public MessageId $messageId,
        public DateTimeImmutable $timestamp,
        public array $meta,
    ) {
    }
}

final readonly class ConformanceOrderPlaced implements Event
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

final readonly class ConformanceUnmappedEvent implements Event
{
    public function __construct(private string $value)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self($data['value']);
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
