<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

/**
 * Class MappedEvent
 *
 * Immutable storage-facing identity, schema, and payload data
 */
final readonly class MappedEvent
{
    /**
     * Constructs MappedEvent
     *
     * @param string               $eventName
     * @param integer              $schemaVersion
     * @param array<string, mixed> $data
     */
    public function __construct(
        private string $eventName,
        private int $schemaVersion,
        private array $data
    ) {
    }

    /**
     * Returns the canonical stable event name
     */
    public function eventName(): string
    {
        return $this->eventName;
    }

    /**
     * Returns the persisted payload schema version
     */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    /**
     * Returns the payload data
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
