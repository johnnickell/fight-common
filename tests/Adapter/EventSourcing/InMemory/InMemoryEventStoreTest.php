<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\InMemory;

use DateTimeImmutable;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryEventStore;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryEventRecord;
use Fight\Common\Domain\EventSourcing\AggregateRoot;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventMapping;
use Fight\Common\Domain\EventSourcing\EventStore;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Identity\UniqueId;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\EventSourcing\ConformanceHistoricalEventRecord;
use Fight\Test\Common\TestCase\EventSourcing\EventStoreConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(InMemoryEventStore::class)]
#[CoversClass(InMemoryEventRecord::class)]
final class InMemoryEventStoreTest extends EventStoreConformanceTestCase
{
    public function test_that_recorded_events_round_trip_through_the_store_and_reconstitute_current_state(): void
    {
        $aggregateId = JourneyAggregateId::fromString('94d34cc2-b44e-4421-9db2-40b57d45f3dd');
        $aggregate = JourneyAggregate::create($aggregateId, 'original');
        $aggregate->rename('current');
        $events = $aggregate->releaseEvents();
        $eventMapper = new EventMapper([]);
        $eventMapper->register('journey', new EventMapping('aggregate-created', JourneyAggregateCreated::class, 1));
        $eventMapper->register('journey', new EventMapping('aggregate-renamed', JourneyAggregateRenamed::class, 1));
        $eventStore = $this->createEventStore($eventMapper);
        $streamId = new StreamId('journey-aggregate', $aggregateId->toString());
        $messages = [
            new EventMessage(
                MessageId::fromString('71f09bb0-fba1-4a2c-9e12-141064f6cdb5'),
                new DateTimeImmutable('2026-08-02T14:00:00.123456+00:00'),
                $events[0],
                Meta::create(['trace_id' => 'journey-create']),
            ),
            new EventMessage(
                MessageId::fromString('e18cfa6d-d42c-47ce-8aab-abfe33cf9ca9'),
                new DateTimeImmutable('2026-08-02T14:00:01.654321+00:00'),
                $events[1],
                Meta::create(['trace_id' => 'journey-rename']),
            ),
        ];

        $eventStore->append($streamId, 0, $messages);
        $storedEvents = [...$eventStore->readStream($streamId)];
        $restored = JourneyAggregate::reconstitute(array_map(
            static fn ($storedEvent): Event => $storedEvent->message()->payload(),
            $storedEvents,
        ));

        self::assertSame(
            [JourneyAggregateCreated::class, JourneyAggregateRenamed::class],
            array_map(static fn ($storedEvent): string => $storedEvent->message()->payload()::class, $storedEvents),
        );
        self::assertSame($aggregateId->toString(), $restored->id()->toString());
        self::assertSame('current', $restored->name());
        self::assertSame(2, $restored->version());
        self::assertSame([], $restored->releaseEvents());
    }

    protected function createEventStore(EventMapper $eventMapper): EventStore
    {
        return new InMemoryEventStore($eventMapper);
    }

    protected function createEventStoreWithHistory(EventMapper $eventMapper, array $historicalRecords): EventStore
    {
        return new InMemoryEventStore($eventMapper, array_map(
            static fn (ConformanceHistoricalEventRecord $record): InMemoryEventRecord => new InMemoryEventRecord(
                $record->streamId,
                $record->eventName,
                $record->schemaVersion,
                $record->streamVersion,
                $record->globalPosition,
                $record->data,
                $record->messageId,
                $record->timestamp,
                $record->meta,
            ),
            $historicalRecords,
        ));
    }
}

final readonly class JourneyAggregateId extends UniqueId
{
}

final readonly class JourneyAggregateCreated implements Event
{
    public function __construct(
        public JourneyAggregateId $id,
        public string $name,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new self(JourneyAggregateId::fromString($data['id']), $data['name']);
    }

    public function toArray(): array
    {
        return ['id' => $this->id->toString(), 'name' => $this->name];
    }
}

final readonly class JourneyAggregateRenamed implements Event
{
    public function __construct(public string $name)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self($data['name']);
    }

    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}

final class JourneyAggregate extends AggregateRoot
{
    private string $name = '';

    private function __construct(JourneyAggregateId $id)
    {
        parent::__construct($id);
    }

    public static function create(JourneyAggregateId $id, string $name): self
    {
        $aggregate = new self($id);
        $aggregate->record(new JourneyAggregateCreated($id, $name));

        return $aggregate;
    }

    public static function reconstitute(iterable $events): static
    {
        $aggregate = null;

        foreach ($events as $event) {
            if (null === $aggregate) {
                if (!$event instanceof JourneyAggregateCreated) {
                    throw new RuntimeException('Journey history must begin with its creation event.');
                }

                $aggregate = new static($event->id);
            }

            $aggregate->replay($event);
        }

        return $aggregate ?? throw new RuntimeException('Journey history cannot be empty.');
    }

    public function rename(string $name): void
    {
        $this->record(new JourneyAggregateRenamed($name));
    }

    public function name(): string
    {
        return $this->name;
    }

    protected function apply(Event $event): void
    {
        match (true) {
            $event instanceof JourneyAggregateCreated => $this->name = $event->name,
            $event instanceof JourneyAggregateRenamed => $this->name = $event->name,
            default => throw new RuntimeException(sprintf('Unsupported journey event: %s.', $event::class)),
        };
    }
}
