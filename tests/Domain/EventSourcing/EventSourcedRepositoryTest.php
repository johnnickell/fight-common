<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\EventSourcing;

use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryEventStore;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\EventSourcing\AggregateDefinition;
use Fight\Common\Domain\EventSourcing\AggregateRoot;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventMapping;
use Fight\Common\Domain\EventSourcing\EventSourcedAggregate;
use Fight\Common\Domain\EventSourcing\EventSourcedRepository;
use Fight\Common\Domain\EventSourcing\EventStore;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Identity\Identifier;
use Fight\Common\Domain\Identity\UniqueId;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(EventSourcedRepository::class)]
class EventSourcedRepositoryTest extends UnitTestCase
{
    public function test_that_save_and_find_round_trip_a_released_event_batch(): void
    {
        $id = RepositoryAggregateId::fromString('6ff84280-669f-40ba-b974-dc27587b17aa');
        $eventMapper = new EventMapper([]);
        $eventMapper->register(
            'repository-test',
            new EventMapping('aggregate-created', RepositoryAggregateCreated::class, 1),
        );
        $eventMapper->register(
            'repository-test',
            new EventMapping('aggregate-renamed', RepositoryAggregateRenamed::class, 1),
        );
        $repository = new EventSourcedRepository(
            new InMemoryEventStore($eventMapper),
            new AggregateDefinition('orders', RepositoryAggregate::class),
        );
        $aggregate = RepositoryAggregate::create($id, 'created');
        $aggregate->rename('saved');

        $repository->save($aggregate);
        $restored = $repository->find($id);

        self::assertSame([], $aggregate->releaseEvents());
        self::assertInstanceOf(RepositoryAggregate::class, $restored);
        self::assertSame($id->toString(), $restored->id()->toString());
        self::assertSame('saved', $restored->name());
        self::assertSame(2, $restored->version());
        self::assertSame([], $restored->releaseEvents());
    }

    public function test_that_save_rejects_an_unconfigured_aggregate_before_releasing_or_writing(): void
    {
        $aggregate = new UnconfiguredAggregate(
            MissingAggregateId::fromString('6ff84280-669f-40ba-b974-dc27587b17aa'),
        );
        $repository = new EventSourcedRepository(
            new EmptyStreamEventStore(),
            new AggregateDefinition('orders', RepositoryAggregate::class),
        );

        try {
            $repository->save($aggregate);
            self::fail('Saving an unconfigured aggregate should fail.');
        } catch (DomainException $exception) {
            self::assertSame('Aggregate must match the configured aggregate class.', $exception->getMessage());
        }

        self::assertFalse($aggregate->releaseEventsCalled);
    }

    public function test_that_save_does_not_append_an_empty_released_batch(): void
    {
        $aggregate = RepositoryAggregate::create(
            RepositoryAggregateId::fromString('6ff84280-669f-40ba-b974-dc27587b17aa'),
            'created',
        );
        $aggregate->releaseEvents();
        $eventStore = new RecordingEventStore();
        $repository = new EventSourcedRepository(
            $eventStore,
            new AggregateDefinition('orders', RepositoryAggregate::class),
        );

        $repository->save($aggregate);

        self::assertSame(0, $eventStore->appendCalls);
    }

    public function test_that_save_derives_the_existing_stream_version_before_a_multi_event_batch(): void
    {
        $id = RepositoryAggregateId::fromString('6ff84280-669f-40ba-b974-dc27587b17aa');
        $aggregate = RepositoryAggregate::reconstitute([
            new RepositoryAggregateCreated($id, 'created'),
            new RepositoryAggregateRenamed('existing'),
        ]);
        $aggregate->rename('first new name');
        $aggregate->rename('second new name');
        $eventStore = new RecordingEventStore();
        $repository = new EventSourcedRepository(
            $eventStore,
            new AggregateDefinition('orders', RepositoryAggregate::class),
        );

        $repository->save($aggregate);

        self::assertSame(2, $eventStore->expectedVersion);
        self::assertSame('orders', $eventStore->streamId?->aggregateName());
        self::assertSame($id->toString(), $eventStore->streamId?->identifier());
        self::assertCount(2, $eventStore->messages);
        self::assertInstanceOf(RepositoryAggregateRenamed::class, $eventStore->messages[0]->payload());
        self::assertSame('first new name', $eventStore->messages[0]->payload()->name);
        self::assertInstanceOf(RepositoryAggregateRenamed::class, $eventStore->messages[1]->payload());
        self::assertSame('second new name', $eventStore->messages[1]->payload()->name);
    }

    public function test_that_save_propagates_append_failure_after_releasing_the_batch(): void
    {
        $aggregate = RepositoryAggregate::create(
            RepositoryAggregateId::fromString('6ff84280-669f-40ba-b974-dc27587b17aa'),
            'created',
        );
        $repository = new EventSourcedRepository(
            new ThrowingEventStore(),
            new AggregateDefinition('orders', RepositoryAggregate::class),
        );

        try {
            $repository->save($aggregate);
            self::fail('An append failure should propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('Append failed.', $exception->getMessage());
        }

        self::assertSame([], $aggregate->releaseEvents());
    }

    public function test_that_find_reconstitutes_ordered_plain_history_through_the_aggregate_contract(): void
    {
        $id = RepositoryAggregateId::fromString('6ff84280-669f-40ba-b974-dc27587b17aa');
        $eventMapper = new EventMapper([]);
        $eventMapper->register(
            'repository-test',
            new EventMapping('aggregate-created', RepositoryAggregateCreated::class, 1),
        );
        $eventMapper->register(
            'repository-test',
            new EventMapping('aggregate-renamed', RepositoryAggregateRenamed::class, 1),
        );
        $eventStore = new InMemoryEventStore($eventMapper);
        $eventStore->append(
            new StreamId('orders', $id->toString()),
            0,
            [
                EventMessage::create(new RepositoryAggregateCreated($id, 'created')),
                EventMessage::create(new RepositoryAggregateRenamed('first')),
                EventMessage::create(new RepositoryAggregateRenamed('restored')),
            ],
        );
        $repository = new EventSourcedRepository(
            $eventStore,
            new AggregateDefinition('orders', RepositoryAggregate::class),
        );

        $aggregate = $repository->find($id);

        self::assertInstanceOf(RepositoryAggregate::class, $aggregate);
        self::assertSame($id->toString(), $aggregate->id()->toString());
        self::assertSame('restored', $aggregate->name());
        self::assertSame(3, $aggregate->version());
        self::assertSame([], $aggregate->releaseEvents());
    }

    public function test_that_find_returns_null_for_an_empty_stable_stream_without_reconstituting(): void
    {
        $eventStore = new EmptyStreamEventStore();
        $repository = new EventSourcedRepository(
            $eventStore,
            new AggregateDefinition('orders', MissingAggregate::class),
        );
        $id = MissingAggregateId::fromString('6ff84280-669f-40ba-b974-dc27587b17aa');

        self::assertNull($repository->find($id));
        self::assertSame('orders', $eventStore->readStreamId->aggregateName());
        self::assertSame($id->toString(), $eventStore->readStreamId->identifier());
    }
}

final class EmptyStreamEventStore implements EventStore
{
    public ?StreamId $readStreamId = null;

    public function append(StreamId $streamId, int $expectedVersion, array $messages): void
    {
        throw new RuntimeException('Not needed by this test.');
    }

    public function readStream(StreamId $streamId): iterable
    {
        $this->readStreamId = $streamId;

        return [];
    }

    public function readAllAfter(int $globalPosition, int $limit): iterable
    {
        throw new RuntimeException('Not needed by this test.');
    }
}

final class RecordingEventStore implements EventStore
{
    public int $appendCalls = 0;

    public ?StreamId $streamId = null;

    public ?int $expectedVersion = null;

    /** @var list<EventMessage> */
    public array $messages = [];

    public function append(StreamId $streamId, int $expectedVersion, array $messages): void
    {
        ++$this->appendCalls;
        $this->streamId = $streamId;
        $this->expectedVersion = $expectedVersion;
        $this->messages = $messages;
    }

    public function readStream(StreamId $streamId): iterable
    {
        throw new RuntimeException('Not needed by this test.');
    }

    public function readAllAfter(int $globalPosition, int $limit): iterable
    {
        throw new RuntimeException('Not needed by this test.');
    }
}

final class ThrowingEventStore implements EventStore
{
    public function append(StreamId $streamId, int $expectedVersion, array $messages): void
    {
        throw new RuntimeException('Append failed.');
    }

    public function readStream(StreamId $streamId): iterable
    {
        throw new RuntimeException('Not needed by this test.');
    }

    public function readAllAfter(int $globalPosition, int $limit): iterable
    {
        throw new RuntimeException('Not needed by this test.');
    }
}

final readonly class MissingAggregateId extends UniqueId
{
}

final readonly class RepositoryAggregateId extends UniqueId
{
}

final readonly class RepositoryAggregateCreated implements Event
{
    public function __construct(
        public RepositoryAggregateId $id,
        public string $name,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new self(RepositoryAggregateId::fromString($data['id']), $data['name']);
    }

    public function toArray(): array
    {
        return ['id' => $this->id->toString(), 'name' => $this->name];
    }
}

final readonly class RepositoryAggregateRenamed implements Event
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

final class RepositoryAggregate extends AggregateRoot
{
    private string $name = '';

    private function __construct(RepositoryAggregateId $id)
    {
        parent::__construct($id);
    }

    public static function create(RepositoryAggregateId $id, string $name): self
    {
        $aggregate = new self($id);
        $aggregate->record(new RepositoryAggregateCreated($id, $name));

        return $aggregate;
    }

    public function rename(string $name): void
    {
        $this->record(new RepositoryAggregateRenamed($name));
    }

    public static function reconstitute(iterable $events): static
    {
        $aggregate = null;

        foreach ($events as $event) {
            if (null === $aggregate) {
                if (!$event instanceof RepositoryAggregateCreated) {
                    throw new RuntimeException('Aggregate history must begin with its creation event.');
                }

                $aggregate = new static($event->id);
            }

            $aggregate->replay($event);
        }

        return $aggregate ?? throw new RuntimeException('Aggregate history cannot be empty.');
    }

    public function name(): string
    {
        return $this->name;
    }

    protected function apply(Event $event): void
    {
        match (true) {
            $event instanceof RepositoryAggregateCreated => $this->whenAggregateCreated($event),
            $event instanceof RepositoryAggregateRenamed => $this->whenAggregateRenamed($event),
            default => throw new RuntimeException('Unrecognized repository test event.'),
        };
    }

    private function whenAggregateCreated(RepositoryAggregateCreated $event): void
    {
        $this->name = $event->name;
    }

    private function whenAggregateRenamed(RepositoryAggregateRenamed $event): void
    {
        $this->name = $event->name;
    }
}

final class MissingAggregate implements EventSourcedAggregate
{
    public function id(): Identifier
    {
        throw new RuntimeException('Not needed by this test.');
    }

    public function version(): int
    {
        return 0;
    }

    public function releaseEvents(): array
    {
        return [];
    }

    public static function reconstitute(iterable $events): static
    {
        throw new RuntimeException('Empty history must not invoke aggregate reconstitution.');
    }
}

final class UnconfiguredAggregate implements EventSourcedAggregate
{
    public bool $releaseEventsCalled = false;

    public function __construct(private readonly Identifier $id)
    {
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function version(): int
    {
        return 0;
    }

    public function releaseEvents(): array
    {
        $this->releaseEventsCalled = true;

        return [];
    }

    public static function reconstitute(iterable $events): static
    {
        throw new RuntimeException('Not needed by this test.');
    }
}
