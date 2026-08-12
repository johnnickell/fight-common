<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Event\EventMessage;

/**
 * Class StoredEvent
 *
 * Immutable envelope for one stored event occurrence
 */
final readonly class StoredEvent
{
    /**
     * Constructs StoredEvent
     */
    public function __construct(
        private StreamId $streamId,
        private string $eventName,
        private int $schemaVersion,
        private int $streamVersion,
        private int $globalPosition,
        private EventMessage $message,
    ) {
        if ('' === $eventName) {
            throw new DomainException('Event name cannot be empty.');
        }

        if (1 > $schemaVersion) {
            throw new DomainException('Schema version must begin at one.');
        }

        if (1 > $streamVersion) {
            throw new DomainException('Stream version must begin at one.');
        }

        if (1 > $globalPosition) {
            throw new DomainException('Global position must begin at one.');
        }
    }

    /**
     * Returns the event stream identity
     */
    public function streamId(): StreamId
    {
        return $this->streamId;
    }

    /**
     * Returns the stable event name
     */
    public function eventName(): string
    {
        return $this->eventName;
    }

    /**
     * Returns the stored payload schema version
     */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    /**
     * Returns the event position within its stream
     */
    public function streamVersion(): int
    {
        return $this->streamVersion;
    }

    /**
     * Returns the event position in global order
     */
    public function globalPosition(): int
    {
        return $this->globalPosition;
    }

    /**
     * Returns the current hydrated event message
     */
    public function message(): EventMessage
    {
        return $this->message;
    }
}
