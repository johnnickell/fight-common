<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverFailure;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreSchema;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventStore;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\EventSourcing\ConformanceEventMappingProvider;
use Fight\Test\Common\TestCase\EventSourcing\ConformanceHistoricalEventRecord;
use Fight\Test\Common\TestCase\EventSourcing\ConformanceOrderPlaced;
use Fight\Test\Common\TestCase\EventSourcing\EventStoreConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalEventStore::class)]
#[CoversClass(DbalEventStoreSchema::class)]
final class DbalEventStoreTest extends EventStoreConformanceTestCase
{
    public function test_that_unsupported_database_platforms_fail_explicitly(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')
            ->once()
            ->andReturn(new OraclePlatform());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'DbalEventStore supports SQLite, MySQL-compatible, and PostgreSQL only; received Doctrine\\DBAL\\Platforms\\OraclePlatform.',
        );

        new DbalEventStore(
            $connection,
            new EventMapper([new ConformanceEventMappingProvider()]),
        );
    }

    public function test_that_a_unique_race_becomes_an_optimistic_conflict_only_after_rollback_proves_a_stream_version_conflict(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')
            ->once()
            ->andReturn(new \Doctrine\DBAL\Platforms\SQLitePlatform());
        $connection->shouldReceive('transactional')
            ->once()
            ->andThrow($this->uniqueConstraintViolation());
        $connection->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn(false);
        $connection->shouldReceive('fetchOne')
            ->once()
            ->with(
                'SELECT COUNT(*) FROM event_store_events WHERE aggregate_name = ? AND aggregate_identifier = ?',
                ['order', 'order-42'],
            )
            ->andReturn(1);
        $eventStore = new DbalEventStore(
            $connection,
            new EventMapper([new ConformanceEventMappingProvider()]),
        );

        try {
            $eventStore->append(new StreamId('order', 'order-42'), 0, [$this->message(
                '6ba7b842-9dad-11d1-80b4-00c04fd430c8',
                'order-42',
            )]);
            self::fail('A proven concurrent stream append must fail optimistically.');
        } catch (\Fight\Common\Domain\EventSourcing\Exception\OptimisticConcurrencyException $exception) {
            self::assertSame(0, $exception->expectedVersion());
            self::assertSame(1, $exception->actualVersion());
        }
    }

    public function test_that_a_unique_race_succeeds_when_post_rollback_reads_prove_an_exact_retry(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')
            ->once()
            ->andReturn(new \Doctrine\DBAL\Platforms\SQLitePlatform());
        $connection->shouldReceive('transactional')
            ->once()
            ->andThrow($this->uniqueConstraintViolation());
        $connection->shouldReceive('fetchAssociative')
            ->once()
            ->with(
                'SELECT aggregate_name, aggregate_identifier, stream_version FROM event_store_events WHERE message_id = ?',
                ['6ba7b847-9dad-11d1-80b4-00c04fd430c8'],
            )
            ->andReturn([
                'aggregate_name' => 'order',
                'aggregate_identifier' => 'order-42',
                'stream_version' => 1,
            ]);
        $connection->shouldReceive('fetchAssociative')
            ->once()
            ->with(
                'SELECT aggregate_name, aggregate_identifier, stream_version FROM event_store_events WHERE message_id = ?',
                ['6ba7b848-9dad-11d1-80b4-00c04fd430c8'],
            )
            ->andReturn([
                'aggregate_name' => 'order',
                'aggregate_identifier' => 'order-42',
                'stream_version' => 2,
            ]);
        $eventStore = new DbalEventStore(
            $connection,
            new EventMapper([new ConformanceEventMappingProvider()]),
        );

        $eventStore->append(new StreamId('order', 'order-42'), 0, [
            $this->message('6ba7b847-9dad-11d1-80b4-00c04fd430c8', 'retry-payload-one'),
            new EventMessage(
                MessageId::fromString('6ba7b848-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T10:16:00.000019+00:00'),
                new ConformanceOrderPlaced('retry-payload-two'),
                Meta::create(['attempt' => 'retry']),
            ),
        ]);
    }

    public function test_that_a_unique_race_becomes_an_optimistic_conflict_when_post_rollback_read_proves_a_message_id_conflict(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')
            ->once()
            ->andReturn(new \Doctrine\DBAL\Platforms\SQLitePlatform());
        $connection->shouldReceive('transactional')
            ->once()
            ->andThrow($this->uniqueConstraintViolation());
        $connection->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn(false);
        $connection->shouldReceive('fetchOne')
            ->once()
            ->with(
                'SELECT COUNT(*) FROM event_store_events WHERE aggregate_name = ? AND aggregate_identifier = ?',
                ['order', 'order-42'],
            )
            ->andReturn(0);
        $connection->shouldReceive('fetchOne')
            ->once()
            ->with(
                'SELECT message_id FROM event_store_events WHERE message_id = ?',
                ['6ba7b843-9dad-11d1-80b4-00c04fd430c8'],
            )
            ->andReturn('6ba7b843-9dad-11d1-80b4-00c04fd430c8');
        $eventStore = new DbalEventStore(
            $connection,
            new EventMapper([new ConformanceEventMappingProvider()]),
        );

        $this->expectException(
            \Fight\Common\Domain\EventSourcing\Exception\OptimisticConcurrencyException::class,
        );

        $eventStore->append(new StreamId('order', 'order-42'), 0, [$this->message(
            '6ba7b843-9dad-11d1-80b4-00c04fd430c8',
            'order-42',
        )]);
    }

    public function test_that_an_unrelated_unique_constraint_failure_propagates_after_post_rollback_reads(): void
    {
        $failure = $this->uniqueConstraintViolation();
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')
            ->once()
            ->andReturn(new \Doctrine\DBAL\Platforms\SQLitePlatform());
        $connection->shouldReceive('transactional')->once()->andThrow($failure);
        $connection->shouldReceive('fetchAssociative')->once()->andReturn(false);
        $connection->shouldReceive('fetchOne')->once()->andReturn(0);
        $connection->shouldReceive('fetchOne')->once()->andReturn(false);
        $eventStore = new DbalEventStore(
            $connection,
            new EventMapper([new ConformanceEventMappingProvider()]),
        );

        try {
            $eventStore->append(new StreamId('order', 'order-42'), 0, [$this->message(
                '6ba7b844-9dad-11d1-80b4-00c04fd430c8',
                'order-42',
            )]);
            self::fail('An unrelated unique constraint failure must propagate unchanged.');
        } catch (UniqueConstraintViolationException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function test_that_json_failures_propagate_and_roll_back_the_append(): void
    {
        $connection = $this->createConnection();
        (new DbalEventStoreSchema())->install($connection);
        $eventStore = new DbalEventStore(
            $connection,
            new EventMapper([new ConformanceEventMappingProvider()]),
        );

        try {
            $eventStore->append(new StreamId('order', 'order-42'), 0, [$this->message(
                '6ba7b845-9dad-11d1-80b4-00c04fd430c8',
                "\xB1",
            )]);
            self::fail('Invalid JSON data must propagate from append.');
        } catch (\JsonException $exception) {
            self::assertSame(JSON_ERROR_UTF8, $exception->getCode());
        }

        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM event_store_events'));
        self::assertSame(0, $connection->fetchOne(
            'SELECT position FROM event_store_global_position WHERE singleton = ?',
            [1],
        ));
    }

    public function test_that_commit_failures_propagate_without_unique_race_classification(): void
    {
        $failure = new \RuntimeException('forced commit failure');
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')
            ->once()
            ->andReturn(new \Doctrine\DBAL\Platforms\SQLitePlatform());
        $connection->shouldReceive('transactional')->once()->andThrow($failure);
        $eventStore = new DbalEventStore(
            $connection,
            new EventMapper([new ConformanceEventMappingProvider()]),
        );

        try {
            $eventStore->append(new StreamId('order', 'order-42'), 0, [$this->message(
                '6ba7b846-9dad-11d1-80b4-00c04fd430c8',
                'order-42',
            )]);
            self::fail('A commit failure must propagate unchanged.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function test_that_schema_installation_creates_the_public_event_store_tables(): void
    {
        $connection = $this->createConnection();

        (new DbalEventStoreSchema())->install($connection);

        self::assertTrue($connection->createSchemaManager()->tablesExist([
            'event_store_events',
            'event_store_global_position',
        ]));
    }

    public function test_that_sqlite_lock_and_database_failures_roll_back_before_contiguous_retry(): void
    {
        $databasePath = sprintf(
            '%s/fight-common-event-store-%s.sqlite',
            sys_get_temp_dir(),
            bin2hex(random_bytes(8)),
        );
        $lockingConnection = $this->createFileConnection($databasePath);
        $appendingConnection = $this->createFileConnection($databasePath);

        try {
            (new DbalEventStoreSchema())->install($lockingConnection);
            $appendingConnection->executeStatement('PRAGMA busy_timeout = 25');
            $eventStore = new DbalEventStore(
                $appendingConnection,
                new EventMapper([new ConformanceEventMappingProvider()]),
            );
            $firstStream = new StreamId('order', 'order-42');
            $firstMessage = new EventMessage(
                MessageId::fromString('6ba7b83c-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-02T10:12:00.000015+00:00'),
                new ConformanceOrderPlaced('order-42'),
                Meta::create(),
            );

            $lockingConnection->beginTransaction();
            $lockingConnection->executeStatement(
                'UPDATE event_store_global_position SET position = position WHERE singleton = ?',
                [1],
            );

            try {
                $eventStore->append($firstStream, 0, [$firstMessage]);
                self::fail('A concurrent SQLite writer must fail within the configured timeout.');
            } catch (DriverException $exception) {
                self::assertStringContainsString('database is locked', $exception->getMessage());
            }

            self::assertSame(0, $appendingConnection->fetchOne('SELECT COUNT(*) FROM event_store_events'));
            $lockingConnection->rollBack();
            $eventStore->append($firstStream, 0, [$firstMessage]);

            $appendingConnection->executeStatement(
                'CREATE TRIGGER event_store_fail_second_insert '
                . 'BEFORE INSERT ON event_store_events '
                . "WHEN NEW.message_id = '6ba7b83e-9dad-11d1-80b4-00c04fd430c8' "
                . "BEGIN SELECT RAISE(ABORT, 'forced database failure'); END",
            );
            $secondStream = new StreamId('order', 'order-43');
            $secondBatch = [
                new EventMessage(
                    MessageId::fromString('6ba7b83d-9dad-11d1-80b4-00c04fd430c8'),
                    new DateTimeImmutable('2026-08-02T10:13:00.000016+00:00'),
                    new ConformanceOrderPlaced('order-43-first'),
                    Meta::create(),
                ),
                new EventMessage(
                    MessageId::fromString('6ba7b83e-9dad-11d1-80b4-00c04fd430c8'),
                    new DateTimeImmutable('2026-08-02T10:14:00.000017+00:00'),
                    new ConformanceOrderPlaced('order-43-second'),
                    Meta::create(),
                ),
            ];

            try {
                $eventStore->append($secondStream, 0, $secondBatch);
                self::fail('A database failure must roll back the complete event batch.');
            } catch (DriverException $exception) {
                self::assertStringContainsString('forced database failure', $exception->getMessage());
            }

            self::assertSame(1, $appendingConnection->fetchOne('SELECT COUNT(*) FROM event_store_events'));
            self::assertSame(1, $appendingConnection->fetchOne(
                'SELECT position FROM event_store_global_position WHERE singleton = ?',
                [1],
            ));

            $appendingConnection->executeStatement('DROP TRIGGER event_store_fail_second_insert');
            $eventStore->append($secondStream, 0, $secondBatch);

            self::assertSame(
                [1, 2, 3],
                array_map(
                    static fn ($event): int => $event->globalPosition(),
                    [...$eventStore->readAllAfter(0, 10)],
                ),
            );
        } finally {
            if ($lockingConnection->isTransactionActive()) {
                $lockingConnection->rollBack();
            }

            $lockingConnection->close();
            $appendingConnection->close();

            if (file_exists($databasePath)) {
                unlink($databasePath);
            }
        }
    }

    protected function createEventStore(EventMapper $eventMapper): EventStore
    {
        $connection = $this->createConnection();
        (new DbalEventStoreSchema())->install($connection);

        return new DbalEventStore($connection, $eventMapper);
    }

    protected function createEventStoreWithHistory(
        EventMapper $eventMapper,
        array $historicalRecords,
    ): EventStore {
        $connection = $this->createConnection();
        (new DbalEventStoreSchema())->install($connection);

        foreach ($historicalRecords as $record) {
            $this->insertHistoricalRecord($connection, $record);
        }

        $connection->update(
            'event_store_global_position',
            ['position' => count($historicalRecords)],
            ['singleton' => 1],
        );

        return new DbalEventStore($connection, $eventMapper);
    }

    private function createConnection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private function createFileConnection(string $databasePath): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $databasePath]);
    }

    private function insertHistoricalRecord(
        Connection $connection,
        ConformanceHistoricalEventRecord $record,
    ): void {
        $connection->insert('event_store_events', [
            'aggregate_name' => $record->streamId->aggregateName(),
            'aggregate_identifier' => $record->streamId->identifier(),
            'stream_version' => $record->streamVersion,
            'global_position' => $record->globalPosition,
            'event_name' => $record->eventName,
            'schema_version' => $record->schemaVersion,
            'payload' => json_encode($record->data, JSON_THROW_ON_ERROR),
            'message_id' => (string) $record->messageId,
            'message_timestamp' => $record->timestamp->format('Y-m-d\TH:i:s.uP'),
            'message_meta' => json_encode($record->meta, JSON_THROW_ON_ERROR),
        ]);
    }

    private function message(string $messageId, string $orderId): EventMessage
    {
        return new EventMessage(
            MessageId::fromString($messageId),
            new DateTimeImmutable('2026-08-02T10:15:00.000018+00:00'),
            new ConformanceOrderPlaced($orderId),
            Meta::create(),
        );
    }

    private function uniqueConstraintViolation(): UniqueConstraintViolationException
    {
        $driverFailure = new class('forced unique constraint race') extends \RuntimeException implements DriverFailure {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        };

        return new UniqueConstraintViolationException($driverFailure, null);
    }
}
