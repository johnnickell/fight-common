<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\InMemory;

use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\MessageId;

/**
 * Class InMemoryEventRecord
 *
 * In-memory snapshot of one mapped event
 */
final readonly class InMemoryEventRecord
{
    private DateTimeImmutable $timestamp;

    /**
     * Constructs InMemoryEventRecord
     *
     * @param StreamId             $streamId
     * @param string               $eventName
     * @param integer              $schemaVersion
     * @param integer              $streamVersion
     * @param integer              $globalPosition
     * @param array<string, mixed> $data
     * @param MessageId            $messageId
     * @param DateTimeImmutable    $timestamp
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private StreamId $streamId,
        private string $eventName,
        private int $schemaVersion,
        private int $streamVersion,
        private int $globalPosition,
        private array $data,
        private MessageId $messageId,
        DateTimeImmutable $timestamp,
        private array $meta,
    ) {
        $this->timestamp = $timestamp->setTimezone(new DateTimeZone('UTC'));
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
     * Returns the mapped event payload data
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Returns the durable message identity
     */
    public function messageId(): MessageId
    {
        return $this->messageId;
    }

    /**
     * Returns the event timestamp in UTC
     */
    public function timestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Returns the mapped event metadata
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }
}
