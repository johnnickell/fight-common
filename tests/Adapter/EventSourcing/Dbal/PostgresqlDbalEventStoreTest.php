<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
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
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DbalEventStore::class)]
#[CoversClass(DbalEventStoreSchema::class)]
#[Group('server-database')]
final class PostgresqlDbalEventStoreTest extends EventStoreConformanceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::assertIsString(
            getenv('FIGHT_COMMON_POSTGRESQL_DSN'),
            'FIGHT_COMMON_POSTGRESQL_DSN is required for the complete server-database suite.',
        );
    }

    public function test_that_schema_installation_creates_the_public_event_store_tables(): void
    {
        $connection = $this->resetDatabase();

        self::assertTrue($connection->createSchemaManager()->tablesExist([
            'event_store_events',
            'event_store_global_position',
        ]));
    }

    public function test_that_competing_append_waits_for_the_allocation_lock_and_then_remains_contiguous(): void
    {
        $lockingConnection = $this->resetDatabase();
        $appendingConnection = $this->createConnection();
        $eventStore = new DbalEventStore(
            $appendingConnection,
            new EventMapper([new ConformanceEventMappingProvider()]),
        );
        $eventStore->append(new StreamId('order', 'order-42'), 0, [$this->message(
            '6ba7b850-9dad-11d1-80b4-00c04fd430c8',
            'order-42',
        )]);
        $competingStream = new StreamId('order', 'order-43');
        $competingMessage = $this->message(
            '6ba7b851-9dad-11d1-80b4-00c04fd430c8',
            'order-43',
        );

        $lockingConnection->beginTransaction();
        $lockingConnection->fetchOne(
            'SELECT position FROM event_store_global_position WHERE singleton = ? FOR UPDATE',
            [1],
        );
        $appendingConnection->executeStatement("SET lock_timeout = '100ms'");

        try {
            $eventStore->append($competingStream, 0, [$competingMessage]);
            self::fail('A competing append must not commit around the allocation lock.');
        } catch (DriverException $exception) {
            self::assertStringContainsString('canceling statement due to lock timeout', $exception->getMessage());
        }

        self::assertSame(1, (int) $appendingConnection->fetchOne(
            'SELECT COUNT(*) FROM event_store_events',
        ));
        self::assertSame(1, (int) $appendingConnection->fetchOne(
            'SELECT position FROM event_store_global_position WHERE singleton = ?',
            [1],
        ));

        $lockingConnection->commit();
        $eventStore->append($competingStream, 0, [$competingMessage]);

        self::assertSame(
            [1, 2],
            array_map(
                static fn ($event): int => $event->globalPosition(),
                [...$eventStore->readAllAfter(0, 10)],
            ),
        );
    }

    protected function createEventStore(EventMapper $eventMapper): EventStore
    {
        return new DbalEventStore($this->resetDatabase(), $eventMapper);
    }

    protected function createEventStoreWithHistory(
        EventMapper $eventMapper,
        array $historicalRecords,
    ): EventStore {
        $connection = $this->resetDatabase();

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

    private function resetDatabase(): Connection
    {
        $connection = $this->createConnection();
        $connection->executeStatement('DROP TABLE IF EXISTS event_store_events');
        $connection->executeStatement('DROP TABLE IF EXISTS event_store_global_position');
        (new DbalEventStoreSchema())->install($connection);

        return $connection;
    }

    private function createConnection(): Connection
    {
        $dsn = getenv('FIGHT_COMMON_POSTGRESQL_DSN');
        self::assertIsString($dsn);
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($dsn);

        return DriverManager::getConnection($params);
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
}
