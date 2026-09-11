<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventStore;
use Fight\Common\Domain\EventSourcing\Exception\OptimisticConcurrencyException;
use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use InvalidArgumentException;

/**
 * Class DbalEventStore
 *
 * Doctrine DBAL adapter for durable mapped event storage
 */
final readonly class DbalEventStore implements EventStore
{
    /**
     * Constructs DbalEventStore
     */
    public function __construct(
        private Connection $connection,
        private EventMapper $eventMapper
    ) {
        $platform = $this->connection->getDatabasePlatform();

        if (
            !$platform instanceof SQLitePlatform
            && !$platform instanceof AbstractMySQLPlatform
            && !$platform instanceof PostgreSQLPlatform
        ) {
            throw new InvalidArgumentException(sprintf(
                'DbalEventStore supports SQLite, MySQL-compatible, and PostgreSQL only; received %s.',
                $platform::class
            ));
        }
    }

    /**
     * Appends one ordered batch of event messages transactionally
     */
    public function append(StreamId $streamId, int $expectedVersion, array $messages): void
    {
        $mappedMessages = [];

        foreach ($messages as $message) {
            $mappedMessages[] = [$message, $this->eventMapper->map($message)];
        }

        try {
            $this->connection->transactional(function () use (
                $streamId,
                $expectedVersion,
                $messages,
                $mappedMessages,
            ): void {
                $platform = $this->connection->getDatabasePlatform();

                if ($platform instanceof SQLitePlatform) {
                    $this->connection->executeStatement(
                        'UPDATE event_store_global_position SET position = position WHERE singleton = ?',
                        [1]
                    );
                }

                $globalPosition = (int) $this->connection->fetchOne(
                    sprintf(
                        'SELECT position FROM event_store_global_position WHERE singleton = ?%s',
                        $platform instanceof SQLitePlatform ? '' : ' FOR UPDATE'
                    ),
                    [1]
                );

                $actualVersion = $this->streamVersion($streamId);
                $isExactRetry = [] !== $messages;
                $hasExistingMessageId = false;
                $hasDuplicateMessageId = false;
                $messageIds = [];

                foreach ($messages as $offset => $message) {
                    $messageId = (string) $message->id();
                    $record = $this->connection->fetchAssociative(
                        sprintf(
                            'SELECT aggregate_name, aggregate_identifier, stream_version %s',
                            'FROM event_store_events WHERE message_id = ?'
                        ),
                        [$messageId]
                    );

                    if (isset($messageIds[$messageId])) {
                        $hasDuplicateMessageId = true;
                    }

                    $messageIds[$messageId] = true;

                    if (false !== $record) {
                        $hasExistingMessageId = true;
                    }

                    if (
                        false === $record
                        || $record['aggregate_name'] !== $streamId->aggregateName()
                        || $record['aggregate_identifier'] !== $streamId->identifier()
                        || (int) $record['stream_version'] !== $expectedVersion + $offset + 1
                    ) {
                        $isExactRetry = false;
                    }
                }

                if ($isExactRetry) {
                    return;
                }

                if ($hasExistingMessageId || $hasDuplicateMessageId || $expectedVersion !== $actualVersion) {
                    throw new OptimisticConcurrencyException($streamId, $expectedVersion, $actualVersion);
                }

                foreach ($mappedMessages as $offset => [$message, $mappedEvent]) {
                    ++$globalPosition;

                    $this->connection->insert('event_store_events', [
                        'aggregate_name'       => $streamId->aggregateName(),
                        'aggregate_identifier' => $streamId->identifier(),
                        'stream_version'       => $expectedVersion + $offset + 1,
                        'global_position'      => $globalPosition,
                        'event_name'           => $mappedEvent->eventName(),
                        'schema_version'       => $mappedEvent->schemaVersion(),
                        'payload'              => json_encode($mappedEvent->data(), JSON_THROW_ON_ERROR),
                        'message_id'           => (string) $message->id(),
                        'message_timestamp'    => $message->timestamp()
                            ->setTimezone(new DateTimeZone('UTC'))
                            ->format('Y-m-d\TH:i:s.uP'),
                        'message_meta'         => json_encode($message->meta()->toArray(), JSON_THROW_ON_ERROR)
                    ]);
                }

                $this->connection->update(
                    'event_store_global_position',
                    ['position' => $globalPosition],
                    ['singleton' => 1]
                );
            });
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            if ([] !== $messages && $this->isExactRetry($streamId, $expectedVersion, $messages)) {
                return;
            }

            $actualVersion = $this->streamVersion($streamId);

            if (
                ([] !== $messages && $actualVersion > $expectedVersion)
                || $this->hasExistingMessageId($messages)
            ) {
                throw new OptimisticConcurrencyException(
                    $streamId,
                    $expectedVersion,
                    $actualVersion
                );
            }

            throw $uniqueConstraintViolationException;
        }
    }

    /**
     * Reads a stream in ascending stream-version order
     */
    public function readStream(StreamId $streamId): iterable
    {
        $records = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT * FROM event_store_events %s',
                'WHERE aggregate_name = ? AND aggregate_identifier = ? ORDER BY stream_version ASC'
            ),
            [$streamId->aggregateName(), $streamId->identifier()]
        );

        foreach ($records as $record) {
            yield $this->hydrate($record);
        }
    }

    /**
     * Reads committed events strictly after one global position
     */
    public function readAllAfter(int $globalPosition, int $limit): iterable
    {
        $records = $this->connection->fetchAllAssociative(
            'SELECT * FROM event_store_events WHERE global_position > ? ORDER BY global_position ASC LIMIT ?',
            [$globalPosition, $limit],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );

        foreach ($records as $record) {
            yield $this->hydrate($record);
        }
    }

    /**
     * Reports whether every requested identity now occupies its intended position
     *
     * @param StreamId $streamId        Event stream identity
     * @param integer  $expectedVersion Expected stream version
     * @param array    $messages        Events requested for persistence
     *
     * @phpstan-param list<EventMessage> $messages
     */
    private function isExactRetry(StreamId $streamId, int $expectedVersion, array $messages): bool
    {
        foreach ($messages as $offset => $message) {
            $record = $this->connection->fetchAssociative(
                sprintf(
                    'SELECT aggregate_name, aggregate_identifier, stream_version %s',
                    'FROM event_store_events WHERE message_id = ?'
                ),
                [(string) $message->id()]
            );

            if (
                false === $record
                || $record['aggregate_name'] !== $streamId->aggregateName()
                || $record['aggregate_identifier'] !== $streamId->identifier()
                || (int) $record['stream_version'] !== $expectedVersion + $offset + 1
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the current version of one stream
     */
    private function streamVersion(StreamId $streamId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM event_store_events WHERE aggregate_name = ? AND aggregate_identifier = ?',
            [$streamId->aggregateName(), $streamId->identifier()]
        );
    }

    /**
     * Reports whether any requested message identity exists after rollback
     *
     * @param array $messages
     *
     * @phpstan-param list<EventMessage> $messages
     */
    private function hasExistingMessageId(array $messages): bool
    {
        return array_any($messages, fn($message): bool => false !== $this->connection->fetchOne(
            'SELECT message_id FROM event_store_events WHERE message_id = ?',
            [(string) $message->id()]
        ));
    }

    /**
     * Reconstitutes one database record as a stored event
     *
     * @param array<string, mixed> $record
     */
    private function hydrate(array $record): StoredEvent
    {
        $eventName = (string) $record['event_name'];
        $schemaVersion = (int) $record['schema_version'];
        $message = $this->eventMapper->hydrate(
            $eventName,
            $schemaVersion,
            json_decode((string) $record['payload'], true, flags: JSON_THROW_ON_ERROR),
            MessageId::fromString((string) $record['message_id']),
            new DateTimeImmutable((string) $record['message_timestamp'])
                ->setTimezone(new DateTimeZone('UTC')),
            Meta::create(json_decode((string) $record['message_meta'], true, flags: JSON_THROW_ON_ERROR))
        );

        return new StoredEvent(
            new StreamId(
                (string) $record['aggregate_name'],
                (string) $record['aggregate_identifier']
            ),
            $eventName,
            $schemaVersion,
            (int) $record['stream_version'],
            (int) $record['global_position'],
            $message
        );
    }
}
