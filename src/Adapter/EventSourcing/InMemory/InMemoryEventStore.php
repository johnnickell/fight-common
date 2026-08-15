<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\InMemory;

use DateTimeZone;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventStore;
use Fight\Common\Domain\EventSourcing\Exception\OptimisticConcurrencyException;
use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;

/**
 * Class InMemoryEventStore
 *
 * In-memory reference adapter for mapped event storage
 */
final class InMemoryEventStore implements EventStore
{
    /** @var list<InMemoryEventRecord> */
    private array $records = [];

    /**
     * Constructs InMemoryEventStore
     *
     * @param EventMapper $eventMapper
     * @param iterable    $records
     *
     * @phpstan-param iterable<InMemoryEventRecord> $records
     */
    public function __construct(private readonly EventMapper $eventMapper, iterable $records = [])
    {
        $this->records = [...$records];
    }

    /**
     * Appends one ordered batch of event messages to a stream
     */
    public function append(StreamId $streamId, int $expectedVersion, array $messages): void
    {
        $streamVersion = count([...$this->recordsForStream($streamId)]);

        $isExactRetry = [] !== $messages;
        $hasExistingMessageId = false;
        $hasDuplicateMessageId = false;
        $messageIds = [];

        foreach ($messages as $offset => $message) {
            $record = $this->recordForMessageId($message->id());
            $messageId = (string) $message->id();

            if (array_key_exists($messageId, $messageIds)) {
                $hasDuplicateMessageId = true;
            }

            $messageIds[$messageId] = true;

            if ($record instanceof InMemoryEventRecord) {
                $hasExistingMessageId = true;
            }

            if (
                !$record instanceof InMemoryEventRecord
                || !$this->isSameStream($record->streamId(), $streamId)
                || $record->streamVersion() !== $expectedVersion + $offset + 1
            ) {
                $isExactRetry = false;
            }
        }

        if ($isExactRetry) {
            return;
        }

        if ($hasExistingMessageId || $hasDuplicateMessageId || $expectedVersion !== $streamVersion) {
            throw new OptimisticConcurrencyException($streamId, $expectedVersion, $streamVersion);
        }

        $mappedMessages = [];

        foreach ($messages as $message) {
            $mappedMessages[] = [$message, $this->eventMapper->map($message)];
        }

        foreach ($mappedMessages as [$message, $mappedEvent]) {
            ++$streamVersion;

            $this->records[] = new InMemoryEventRecord(
                $streamId,
                $mappedEvent->eventName(),
                $mappedEvent->schemaVersion(),
                $streamVersion,
                count($this->records) + 1,
                $mappedEvent->data(),
                $message->id(),
                $message->timestamp()->setTimezone(new DateTimeZone('UTC')),
                $message->meta()->toArray()
            );
        }
    }

    /**
     * Reads a stream in ascending stream-version order
     */
    public function readStream(StreamId $streamId): iterable
    {
        foreach ($this->recordsForStream($streamId) as $record) {
            yield $this->hydrate($record);
        }
    }

    /**
     * Reads events strictly after a global position
     */
    public function readAllAfter(int $globalPosition, int $limit): iterable
    {
        $yielded = 0;

        foreach ($this->records as $record) {
            if ($record->globalPosition() <= $globalPosition) {
                continue;
            }

            if ($yielded >= $limit) {
                break;
            }

            yield $this->hydrate($record);
            ++$yielded;
        }
    }

    /**
     * Reads records belonging to one event stream
     *
     * @return iterable<InMemoryEventRecord>
     */
    private function recordsForStream(StreamId $streamId): iterable
    {
        foreach ($this->records as $record) {
            if ($this->isSameStream($record->streamId(), $streamId)) {
                yield $record;
            }
        }
    }

    /**
     * Finds the record carrying a message identity
     */
    private function recordForMessageId(MessageId $messageId): ?InMemoryEventRecord
    {
        foreach ($this->records as $record) {
            if ($record->messageId()->equals($messageId)) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Determines whether two stream identities match
     */
    private function isSameStream(StreamId $first, StreamId $second): bool
    {
        return $first->aggregateName() === $second->aggregateName()
            && $first->identifier() === $second->identifier();
    }

    /**
     * Reconstitutes one persisted record as a stored event
     */
    private function hydrate(InMemoryEventRecord $record): StoredEvent
    {
        $message = $this->eventMapper->hydrate(
            $record->eventName(),
            $record->schemaVersion(),
            $record->data(),
            $record->messageId(),
            $record->timestamp(),
            Meta::create($record->meta())
        );

        return new StoredEvent(
            $record->streamId(),
            $record->eventName(),
            $record->schemaVersion(),
            $record->streamVersion(),
            $record->globalPosition(),
            $message
        );
    }
}
