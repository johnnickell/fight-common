<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\EventSourcing;

use DateTimeImmutable;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryEventRecord;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryEventStore;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryProjectionCheckpointStore;
use Fight\Common\Application\EventSourcing\ProjectionCheckpointStore;
use Fight\Common\Application\EventSourcing\ProjectionRunner;
use Fight\Common\Application\EventSourcing\Projector;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventMapping;
use Fight\Common\Domain\EventSourcing\EventMappingProvider;
use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\EventSourcing\Upcaster;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectionRunner::class)]
#[CoversClass(InMemoryProjectionCheckpointStore::class)]
final class ProjectionRunnerTest extends UnitTestCase
{
    public function test_that_resetting_a_stable_projector_rebuilds_history_after_adding_a_handled_type(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new ProjectionEventMappingProvider()]),
            [$this->eventRecord(
                'orders.order-cancelled',
                1,
                ['order_id' => 'order-1'],
                '6ba7b841-9dad-11d1-80b4-00c04fd430c8',
            )],
        );
        $checkpointStore = new InMemoryProjectionCheckpointStore();
        $initialCollector = new ProjectionEventCollector();
        $initialProjector = new class($initialCollector) implements Projector {
            public function __construct(private ProjectionEventCollector $collector)
            {
            }

            public function name(): string
            {
                return 'orders.order-summary';
            }

            public function eventClasses(): iterable
            {
                yield CurrentOrderPlaced::class;
            }

            public function project(StoredEvent $event): void
            {
                $this->collector->events[] = $event;
            }
        };
        $runner = new ProjectionRunner($eventStore, $checkpointStore);

        $runner->run($initialProjector, 10);

        self::assertSame([], $initialCollector->events);
        self::assertSame(1, $checkpointStore->load('orders.order-summary'));

        // The consumer stops its worker and clears or recreates the read model before this reset.
        $checkpointStore->reset('orders.order-summary');
        $rebuiltCollector = new ProjectionEventCollector();
        $replacementProjector = new class($rebuiltCollector) implements Projector {
            public function __construct(private ProjectionEventCollector $collector)
            {
            }

            public function name(): string
            {
                return 'orders.order-summary';
            }

            public function eventClasses(): iterable
            {
                yield CurrentOrderPlaced::class;
                yield CurrentOrderCancelled::class;
            }

            public function project(StoredEvent $event): void
            {
                $this->collector->events[] = $event;
            }
        };

        $runner->run($replacementProjector, 10);

        self::assertCount(1, $rebuiltCollector->events);
        self::assertSame(1, $rebuiltCollector->events[0]->globalPosition());
        self::assertInstanceOf(CurrentOrderCancelled::class, $rebuiltCollector->events[0]->message()->payload());
        self::assertSame(['order_id' => 'order-1'], $rebuiltCollector->events[0]->message()->payload()->toArray());
        self::assertSame(1, $checkpointStore->load('orders.order-summary'));
    }

    public function test_that_a_projector_failure_stops_the_batch_and_resumes_at_the_failed_position(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new ProjectionEventMappingProvider()]),
            [
                $this->eventRecord(
                    'orders.order-placed',
                    1,
                    ['order_id' => 'order-1', 'source' => 'current'],
                    '6ba7b841-9dad-11d1-80b4-00c04fd430c8',
                ),
                $this->eventRecord(
                    'orders.order-placed',
                    2,
                    ['order_id' => 'order-2', 'source' => 'current'],
                    '6ba7b842-9dad-11d1-80b4-00c04fd430c8',
                ),
                $this->eventRecord(
                    'orders.order-placed',
                    3,
                    ['order_id' => 'order-3', 'source' => 'current'],
                    '6ba7b843-9dad-11d1-80b4-00c04fd430c8',
                ),
            ],
        );
        $checkpointStore = new RecordingProjectionCheckpointStore([]);
        $failure = new \RuntimeException('Read model write failed.');
        $projector = new FailingOnceProjectionProjector(2, $failure);
        $runner = new ProjectionRunner($eventStore, $checkpointStore);

        try {
            $runner->run($projector, 3);
            self::fail('Expected the projector failure to propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame([1, 2], $projector->attemptedPositions);
        self::assertSame([['orders.order-summary', 1]], $checkpointStore->saves);
        self::assertSame(1, $checkpointStore->load('orders.order-summary'));

        $runner->run($projector, 3);

        self::assertSame([1, 2, 2, 3], $projector->attemptedPositions);
        self::assertSame(
            [
                ['orders.order-summary', 1],
                ['orders.order-summary', 2],
                ['orders.order-summary', 3],
            ],
            $checkpointStore->saves,
        );
    }

    public function test_that_a_checkpoint_save_failure_redelivers_a_successfully_projected_event(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new ProjectionEventMappingProvider()]),
            [$this->eventRecord(
                'orders.order-placed',
                1,
                ['order_id' => 'order-1', 'source' => 'current'],
                '6ba7b841-9dad-11d1-80b4-00c04fd430c8',
            )],
        );
        $failure = new \RuntimeException('Checkpoint write failed.');
        $checkpointStore = new FailingOnceProjectionCheckpointStore($failure);
        $projector = new RecordingProjectionProjector();
        $runner = new ProjectionRunner($eventStore, $checkpointStore);

        try {
            $runner->run($projector, 1);
            self::fail('Expected the checkpoint failure to propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame([1], $projector->projectedPositions);
        self::assertSame(0, $checkpointStore->load('orders.order-summary'));

        $runner->run($projector, 1);

        self::assertSame([1, 1], $projector->projectedPositions);
        self::assertSame(1, $checkpointStore->load('orders.order-summary'));
    }

    public function test_that_a_batch_skips_undeclared_events_and_checkpoints_each_position_in_order(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new ProjectionEventMappingProvider()]),
            [
                $this->eventRecord(
                    'orders.order-placed',
                    1,
                    ['order_id' => 'order-1', 'source' => 'current'],
                    '6ba7b841-9dad-11d1-80b4-00c04fd430c8',
                ),
                $this->eventRecord(
                    'orders.order-placed',
                    2,
                    ['order_id' => 'order-2', 'source' => 'current'],
                    '6ba7b842-9dad-11d1-80b4-00c04fd430c8',
                ),
                $this->eventRecord(
                    'orders.order-cancelled',
                    3,
                    ['order_id' => 'order-3'],
                    '6ba7b843-9dad-11d1-80b4-00c04fd430c8',
                ),
                $this->eventRecord(
                    'orders.order-placed',
                    4,
                    ['order_id' => 'order-4', 'source' => 'current'],
                    '6ba7b844-9dad-11d1-80b4-00c04fd430c8',
                ),
                $this->eventRecord(
                    'orders.order-placed',
                    5,
                    ['order_id' => 'order-5', 'source' => 'current'],
                    '6ba7b845-9dad-11d1-80b4-00c04fd430c8',
                ),
            ],
        );
        $checkpointStore = new RecordingProjectionCheckpointStore(['orders.order-summary' => 1]);
        $collector = new ProjectionEventCollector();
        $projector = new class($collector) implements Projector {
            public function __construct(private ProjectionEventCollector $collector)
            {
            }

            public function name(): string
            {
                return 'orders.order-summary';
            }

            public function eventClasses(): iterable
            {
                yield CurrentOrderPlaced::class;
            }

            public function project(StoredEvent $event): void
            {
                $this->collector->events[] = $event;
            }
        };

        (new ProjectionRunner($eventStore, $checkpointStore))->run($projector, 3);

        self::assertSame(
            [2, 4],
            array_map(static fn (StoredEvent $event): int => $event->globalPosition(), $collector->events),
        );
        self::assertSame(
            [
                ['orders.order-summary', 2],
                ['orders.order-summary', 3],
                ['orders.order-summary', 4],
            ],
            $checkpointStore->saves,
        );
        self::assertSame(4, $checkpointStore->load('orders.order-summary'));
    }

    public function test_that_a_declared_upcasted_event_is_projected_and_checkpointed_by_stable_name(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new ProjectionEventMappingProvider()]),
            [new InMemoryEventRecord(
                new StreamId('order', 'order-42'),
                'orders.order-placed',
                1,
                1,
                1,
                ['legacy_order_id' => 'order-42'],
                MessageId::fromString('6ba7b840-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-08T09:15:30.123456+00:00'),
                [],
            )],
        );
        $checkpointStore = new InMemoryProjectionCheckpointStore();
        $collector = new ProjectionEventCollector();
        $projector = new class($collector) implements Projector {
            public function __construct(private ProjectionEventCollector $collector)
            {
            }

            public function name(): string
            {
                return 'orders.order-summary';
            }

            public function eventClasses(): iterable
            {
                yield CurrentOrderPlaced::class;
            }

            public function project(StoredEvent $event): void
            {
                $this->collector->events[] = $event;
            }
        };

        (new ProjectionRunner($eventStore, $checkpointStore))->run($projector, 10);

        self::assertCount(1, $collector->events);
        self::assertSame(1, $collector->events[0]->globalPosition());
        self::assertInstanceOf(CurrentOrderPlaced::class, $collector->events[0]->message()->payload());
        self::assertSame(
            ['order_id' => 'order-42', 'source' => 'historical'],
            $collector->events[0]->message()->payload()->toArray(),
        );
        self::assertSame(1, $checkpointStore->load('orders.order-summary'));
    }

    /**
     * Creates one current-schema in-memory event record
     *
     * @param array<string, mixed> $data
     */
    private function eventRecord(string $eventName, int $globalPosition, array $data, string $messageId): InMemoryEventRecord
    {
        return new InMemoryEventRecord(
            new StreamId('order', sprintf('order-%d', $globalPosition)),
            $eventName,
            str_ends_with($eventName, 'order-placed') ? 2 : 1,
            1,
            $globalPosition,
            $data,
            MessageId::fromString($messageId),
            new DateTimeImmutable('2026-08-08T09:15:30.123456+00:00'),
            [],
        );
    }
}

final class ProjectionEventCollector
{
    /** @var list<StoredEvent> */
    public array $events = [];
}

final class RecordingProjectionCheckpointStore implements ProjectionCheckpointStore
{
    /** @var list<array{string, int}> */
    public array $saves = [];

    /** @param array<string, int> $checkpoints */
    public function __construct(private array $checkpoints)
    {
    }

    public function load(string $projectorName): int
    {
        return $this->checkpoints[$projectorName] ?? 0;
    }

    public function save(string $projectorName, int $globalPosition): void
    {
        $this->saves[] = [$projectorName, $globalPosition];
        $this->checkpoints[$projectorName] = $globalPosition;
    }

    public function reset(string $projectorName): void
    {
        $this->checkpoints[$projectorName] = 0;
    }
}

final class FailingOnceProjectionCheckpointStore implements ProjectionCheckpointStore
{
    private int $checkpoint = 0;

    private bool $failed = false;

    public function __construct(private readonly \RuntimeException $failure)
    {
    }

    public function load(string $projectorName): int
    {
        return $this->checkpoint;
    }

    public function save(string $projectorName, int $globalPosition): void
    {
        if (!$this->failed) {
            $this->failed = true;

            throw $this->failure;
        }

        $this->checkpoint = $globalPosition;
    }

    public function reset(string $projectorName): void
    {
        $this->checkpoint = 0;
    }
}

final class FailingOnceProjectionProjector implements Projector
{
    /** @var list<int> */
    public array $attemptedPositions = [];

    private bool $failed = false;

    public function __construct(
        private readonly int $failedPosition,
        private readonly \RuntimeException $failure,
    ) {
    }

    public function name(): string
    {
        return 'orders.order-summary';
    }

    public function eventClasses(): iterable
    {
        yield CurrentOrderPlaced::class;
    }

    public function project(StoredEvent $event): void
    {
        $this->attemptedPositions[] = $event->globalPosition();

        if (!$this->failed && $this->failedPosition === $event->globalPosition()) {
            $this->failed = true;

            throw $this->failure;
        }
    }
}

final class RecordingProjectionProjector implements Projector
{
    /** @var list<int> */
    public array $projectedPositions = [];

    public function name(): string
    {
        return 'orders.order-summary';
    }

    public function eventClasses(): iterable
    {
        yield CurrentOrderPlaced::class;
    }

    public function project(StoredEvent $event): void
    {
        $this->projectedPositions[] = $event->globalPosition();
    }
}

final readonly class ProjectionEventMappingProvider implements EventMappingProvider
{
    public function namespace(): string
    {
        return 'orders';
    }

    public function mappings(): iterable
    {
        yield new EventMapping('order-placed', CurrentOrderPlaced::class, 2, [
            new ProjectionOrderPlacedUpcaster(),
        ]);
        yield new EventMapping('order-cancelled', CurrentOrderCancelled::class, 1);
    }
}

final readonly class ProjectionOrderPlacedUpcaster implements Upcaster
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
        return [
            'order_id' => $data['legacy_order_id'],
            'source' => 'historical',
        ];
    }
}

final readonly class CurrentOrderPlaced implements Event
{
    public function __construct(private string $orderId, private string $source)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self($data['order_id'], $data['source']);
    }

    public function toArray(): array
    {
        return ['order_id' => $this->orderId, 'source' => $this->source];
    }
}

final readonly class CurrentOrderCancelled implements Event
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
