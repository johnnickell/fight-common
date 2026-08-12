<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Identity\Identifier;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;

/**
 * Class EventSourcedRepository
 *
 * Repository for one configured event-sourced aggregate type
 */
final readonly class EventSourcedRepository
{
    /**
     * Constructs EventSourcedRepository
     */
    public function __construct(
        private EventStore $eventStore,
        private AggregateDefinition $definition,
    ) {
    }

    /**
     * Finds an aggregate by its identifier
     */
    public function find(Identifier $id): ?EventSourcedAggregate
    {
        $streamId = new StreamId($this->definition->name(), $id->toString());
        $events = [];

        foreach ($this->eventStore->readStream($streamId) as $storedEvent) {
            /** @var Event $event */
            $event = $storedEvent->message()->payload();
            $events[] = $event;
        }

        if ([] === $events) {
            return null;
        }

        $aggregateClass = $this->definition->aggregateClass();

        return $aggregateClass::reconstitute($events);
    }

    /**
     * Persists newly recorded events for the configured aggregate type
     */
    public function save(EventSourcedAggregate $aggregate): void
    {
        $aggregateClass = $this->definition->aggregateClass();

        if (!$aggregate instanceof $aggregateClass) {
            throw new DomainException('Aggregate must match the configured aggregate class.');
        }

        $events = $aggregate->releaseEvents();

        if ([] === $events) {
            return;
        }

        $messages = [];

        foreach ($events as $event) {
            $messages[] = EventMessage::create($event);
        }

        $streamId = new StreamId($this->definition->name(), $aggregate->id()->toString());
        $expectedVersion = $aggregate->version() - count($events);

        $this->eventStore->append($streamId, $expectedVersion, $messages);
    }
}
