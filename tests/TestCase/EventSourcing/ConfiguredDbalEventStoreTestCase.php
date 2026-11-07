<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreSchema;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreTables;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventStore;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class ConfiguredDbalEventStoreTestCase extends EventStoreConformanceTestCase
{
    abstract protected function createConnection(): Connection;

    protected function createEventStore(EventMapper $eventMapper): EventStore
    {
        $connection = $this->resetDatabase();
        $tables = new DbalEventStoreTables('Order', 'Group');
        (new DbalEventStoreSchema($tables))->install($connection);

        return new DbalEventStore($connection, $eventMapper, $tables);
    }

    protected function createEventStoreWithHistory(EventMapper $eventMapper, array $historicalRecords): EventStore
    {
        $connection = $this->resetDatabase();
        $tables = new DbalEventStoreTables('Order', 'Group');
        (new DbalEventStoreSchema($tables))->install($connection);
        $platform = $connection->getDatabasePlatform();
        foreach ($historicalRecords as $record) {
            $connection->insert($tables->eventTable()->quoted($platform), [
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
        $connection->update(
            $tables->globalPositionTable()->quoted($platform),
            ['position' => count($historicalRecords)],
            ['singleton' => 1],
        );

        return new DbalEventStore($connection, $eventMapper, $tables);
    }

    public static function selections(): iterable
    {
        yield 'both' => ['Order', 'Group'];
        yield 'events only' => ['Order', 'event_store_global_position'];
        yield 'sequence only' => ['event_store_events', 'Group'];
    }

    #[DataProvider('selections')]
    public function test_that_populated_defaults_coexist_without_fallback_or_migration(
        string $eventTable,
        string $positionTable,
    ): void
    {
        $connection = $this->resetDatabase();
        $eventMapper = new EventMapper([new ConformanceEventMappingProvider()]);
        $defaultStore = new DbalEventStore(connection: $connection, eventMapper: $eventMapper);
        $streamId = new StreamId('order', 'shared');
        $originalMessage = EventMessage::create(new ConformanceOrderPlaced('original'));
        $defaultStore->append($streamId, 0, [$originalMessage]);
        $beforeEvents = $connection->fetchAllAssociative('SELECT * FROM event_store_events');
        $tables = new DbalEventStoreTables($eventTable, $positionTable);
        $configuredStore = new DbalEventStore($connection, $eventMapper, $tables);
        self::assertFalse($this->tableExists($connection, 'Order'));
        self::assertFalse($this->tableExists($connection, 'Group'));

        $schema = new DbalEventStoreSchema(tables: $tables);
        $schema->install($connection);
        $schema->install($connection);
        // A sequence-only override intentionally reuses the event table. The operator
        // must initialize the newly selected sequence consistently before appending.
        if ($eventTable === 'event_store_events') {
            self::assertCount(1, [...$configuredStore->readStream($streamId)]);
            $connection->update($tables->globalPositionTable()->quoted($connection->getDatabasePlatform()), ['position' => 1], ['singleton' => 1]);
        } else {
            self::assertSame([], [...$configuredStore->readStream($streamId)]);
            self::assertSame([], [...$configuredStore->readAllAfter(0, 10)]);
        }
        $message = EventMessage::create(new ConformanceOrderPlaced('configured'));
        if ($eventTable !== 'event_store_events') {
            // An identity in overridden default events must not count as a retry/conflict here.
            $message = new EventMessage($originalMessage->id(), $message->timestamp(), $message->payload(), $message->meta());
        }
        $expectedVersion = $eventTable === 'event_store_events' ? 1 : 0;
        $configuredStore->append($streamId, $expectedVersion, [$message]);
        $configuredStore->append($streamId, $expectedVersion, [$message]);
        $events = [...$configuredStore->readStream($streamId)];
        self::assertCount($expectedVersion + 1, $events);
        self::assertSame('configured', $events[$expectedVersion]->message()->payload()->toArray()['order_id']);
        self::assertSame($positionTable === 'event_store_global_position' ? 2 : ($eventTable === 'event_store_events' ? 2 : 1), $events[$expectedVersion]->globalPosition());
        if ($eventTable !== 'event_store_events') {
            self::assertSame($beforeEvents, $connection->fetchAllAssociative('SELECT * FROM event_store_events'));
            self::assertSame('original', [...$defaultStore->readStream($streamId)][0]->message()->payload()->toArray()['order_id']);
        }
        if ($positionTable !== 'event_store_global_position') {
            self::assertSame(1, (int) $connection->fetchOne('SELECT position FROM event_store_global_position WHERE singleton = 1'));
        }
    }

    #[DataProvider('selections')]
    public function test_that_missing_selected_storage_propagates_without_creating_or_falling_back(
        string $eventTable,
        string $positionTable,
    ): void
    {
        $connection = $this->resetDatabase();
        $eventMapper = new EventMapper([new ConformanceEventMappingProvider()]);
        $defaultStore = new DbalEventStore($connection, $eventMapper);
        $streamId = new StreamId('order', 'existing');
        $defaultStore->append($streamId, 0, [EventMessage::create(new ConformanceOrderPlaced('original'))]);
        $configuredStore = new DbalEventStore(
            $connection,
            $eventMapper,
            new DbalEventStoreTables($eventTable, $positionTable),
        );
        try {
            $configuredStore->append($streamId, 0, [EventMessage::create(new ConformanceOrderPlaced('new'))]);
            self::fail('Missing selected storage must propagate a DBAL failure.');
        } catch (Exception) {
            self::assertCount(1, [...$defaultStore->readAllAfter(0, 10)]);
            self::assertFalse($connection->isTransactionActive());
            self::assertFalse($this->tableExists($connection, 'Order'));
            self::assertFalse($this->tableExists($connection, 'Group'));
        }
        if ($eventTable !== 'event_store_events') {
            foreach ([static fn () => [...$configuredStore->readStream($streamId)], static fn () => [...$configuredStore->readAllAfter(0, 10)]] as $read) {
                try {
                    $read();
                    self::fail('Reads must not fall back to populated default events.');
                } catch (Exception) {
                    self::assertCount(1, [...$defaultStore->readStream($streamId)]);
                }
            }
        }
    }

    public function test_that_incompatible_selected_storage_is_not_repaired_or_replaced(): void
    {
        $connection = $this->resetDatabase();
        $connection->executeStatement(
            'CREATE TABLE '.$connection->getDatabasePlatform()->quoteSingleIdentifier('Group').' (unrelated INTEGER)',
        );
        $schema = new DbalEventStoreSchema(new DbalEventStoreTables('Order', 'Group'));
        try {
            $schema->install($connection);
            self::fail('An incompatible selected sequence must fail initialization.');
        } catch (Exception) {
            self::assertTrue($this->tableExists($connection, 'Group'));
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM event_store_events'));
            self::assertSame(0, (int) $connection->fetchOne('SELECT position FROM event_store_global_position WHERE singleton = 1'));
        }
        $connection->createSchemaManager()->dropTable($connection->getDatabasePlatform()->quoteSingleIdentifier('Group'));
        $connection->createSchemaManager()->dropTable($connection->getDatabasePlatform()->quoteSingleIdentifier('Order'));
        $connection->executeStatement(
            'CREATE TABLE '.$connection->getDatabasePlatform()->quoteSingleIdentifier('Order').' (unrelated INTEGER)',
        );
        $schema->install($connection); // Existing installer does not validate/repair event columns.
        $configuredStore = new DbalEventStore(
            $connection,
            new EventMapper([new ConformanceEventMappingProvider()]),
            new DbalEventStoreTables('Order', 'Group'),
        );
        $this->expectException(Exception::class);
        $configuredStore->append(new StreamId('order', 'new'), 0, [EventMessage::create(new ConformanceOrderPlaced('new'))]);
    }

    public function test_that_configured_concurrent_appends_expose_only_committed_prefixes(): void
    {
        $connection = $this->resetDatabase();
        $tables = new DbalEventStoreTables('Order', 'Group');
        (new DbalEventStoreSchema($tables))->install($connection);
        $eventMapper = new EventMapper([new ConformanceEventMappingProvider()]);
        $eventStore = new DbalEventStore($connection, $eventMapper, $tables);
        $observerConnection = $this->createConnection();
        $observerStore = new DbalEventStore($observerConnection, $eventMapper, $tables);
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $connection->executeStatement('PRAGMA journal_mode = WAL');
        }
        $connection->beginTransaction();
        $eventStore->append(new StreamId('order', 'first'), 0, [EventMessage::create(new ConformanceOrderPlaced('first'))]);
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, __DIR__.'/competing-event-append.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        try {
            fwrite($pipes[0], json_encode($connection->getParams(), JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 10);
            self::assertSame("attempting\n", fgets($pipes[1]));
            stream_set_blocking($pipes[1], false);
            usleep(150000);
            self::assertTrue(proc_get_status($process)['running']);
            self::assertSame('', stream_get_contents($pipes[1]));
            self::assertSame([], [...$observerStore->readAllAfter(0, 10)]);
            $connection->commit();
            stream_set_blocking($pipes[1], true);
            self::assertSame("committed\n", stream_get_contents($pipes[1]));
            self::assertSame('', stream_get_contents($pipes[2]));
            self::assertSame(0, proc_close($process));
            $process = null;
            $events = [...$observerStore->readAllAfter(0, 10)];
            self::assertSame([1, 2], array_map(static fn ($event) => $event->globalPosition(), $events));
            self::assertSame(['first', 'second'], array_map(static fn ($event) => $event->message()->payload()->toArray()['order_id'], $events));
            self::assertSame([2], array_map(static fn ($event) => $event->globalPosition(), [...$observerStore->readAllAfter(1, 10)]));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            $observerConnection->close();
            $connection->close();
        }
    }

    private function tableExists(Connection $connection, string $tableName): bool
    {
        return in_array($tableName, array_map(
            static fn ($name) => trim($name, '`"'),
            $connection->createSchemaManager()->listTableNames(),
        ), true);
    }

    private function resetDatabase(): Connection
    {
        $connection = $this->createConnection();
        $schemaManager = $connection->createSchemaManager();
        $existingTables = array_map(static fn ($name) => trim($name, '`"'), $schemaManager->listTableNames());
        foreach (['Order', 'Group', 'event_store_events', 'event_store_global_position'] as $table) {
            if (in_array($table, $existingTables, true)) {
                $schemaManager->dropTable($connection->getDatabasePlatform()->quoteSingleIdentifier($table));
            }
        }
        (new DbalEventStoreSchema())->install($connection);

        return $connection;
    }
}
