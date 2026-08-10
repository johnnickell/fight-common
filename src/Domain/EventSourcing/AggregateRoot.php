<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

use Fight\Common\Domain\Identity\Identifier;
use Fight\Common\Domain\Messaging\Event\Event;

/**
 * Class AggregateRoot
 */
abstract class AggregateRoot implements EventSourcedAggregate
{
    private int $version = 0;
    /** @var list<Event> */
    private array $pendingEvents = [];

    /**
     * Constructs AggregateRoot
     */
    protected function __construct(private readonly Identifier $id)
    {
    }

    /**
     * @inheritDoc
     */
    public function id(): Identifier
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * @inheritDoc
     */
    public function releaseEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        return $events;
    }

    /**
     * Records an event after applying it successfully
     */
    final protected function record(Event $event): void
    {
        $this->apply($event);
        ++$this->version;
        $this->pendingEvents[] = $event;
    }

    /**
     * Applies an event without recording it as pending
     */
    final protected function replay(Event $event): void
    {
        $this->apply($event);
        ++$this->version;
    }

    /**
     * Applies an event through consumer-owned explicit routing
     */
    abstract protected function apply(Event $event): void;
}
