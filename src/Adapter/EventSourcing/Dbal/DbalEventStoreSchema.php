<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

/**
 * Class DbalEventStoreSchema
 *
 * Portable Doctrine DBAL schema for durable event storage
 */
final readonly class DbalEventStoreSchema
{
    /**
     * Constructs DbalEventStoreSchema
     */
    public function __construct(private DbalEventStoreTables $tables = new DbalEventStoreTables())
    {
    }

    /**
     * Returns the event-store schema definition
     */
    public function schema(): Schema
    {
        $schema = new Schema();
        $eventTable = $schema->createTable($this->tables->eventTable()->schemaName());
        $eventTable->addColumn('global_position', 'bigint');
        $eventTable->addColumn('aggregate_name', 'string', ['length' => 255]);
        $eventTable->addColumn('aggregate_identifier', 'string', ['length' => 255]);
        $eventTable->addColumn('stream_version', 'integer');
        $eventTable->addColumn('event_name', 'string', ['length' => 255]);
        $eventTable->addColumn('schema_version', 'integer');
        $eventTable->addColumn('payload', 'text');
        $eventTable->addColumn('message_id', 'string', ['length' => 36]);
        $eventTable->addColumn('message_timestamp', 'string', ['length' => 32]);
        $eventTable->addColumn('message_meta', 'text');
        $eventTable->setPrimaryKey(['global_position']);
        $eventTable->addUniqueIndex(
            ['aggregate_name', 'aggregate_identifier', 'stream_version'],
            $this->tables->eventTable()->name() === 'event_store_events' ? 'event_store_stream_version_unique' : null
        );
        $eventTable->addUniqueIndex(
            ['message_id'],
            $this->tables->eventTable()->name() === 'event_store_events' ? 'event_store_message_id_unique' : null
        );

        $globalPositionTable = $schema->createTable($this->tables->globalPositionTable()->schemaName());
        $globalPositionTable->addColumn('singleton', 'smallint');
        $globalPositionTable->addColumn('position', 'bigint');
        $globalPositionTable->setPrimaryKey(['singleton']);

        return $schema;
    }

    /**
     * Creates the event-store schema and initial global-position state
     */
    public function install(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $schema = $this->schema();
        // PostgreSQL reports case-sensitive names with DBAL quoting markers.
        // Ordinary names cannot contain these delimiters, so compare exact logical names.
        $existingTables = array_map(
            static fn(string $tableName): string => trim($tableName, '`"'),
            $schemaManager->listTableNames()
        );

        foreach ([$this->tables->eventTable(), $this->tables->globalPositionTable()] as $table) {
            if (!in_array($table->name(), $existingTables, true)) {
                $schemaManager->createTable($schema->getTable($table->name()));
            }
        }

        $positionTable = $this->tables->globalPositionTable()->quoted($connection->getDatabasePlatform());

        if (
            false === $connection->fetchOne(
                sprintf('SELECT singleton FROM %s WHERE singleton = ?', $positionTable),
                [1]
            )
        ) {
            $connection->insert($positionTable, [
                'singleton' => 1,
                'position'  => 0
            ]);
        }
    }
}
