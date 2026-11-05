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
        $events = $schema->createTable($this->tables->eventTable()->schemaName());
        $events->addColumn('global_position', 'bigint');
        $events->addColumn('aggregate_name', 'string', ['length' => 255]);
        $events->addColumn('aggregate_identifier', 'string', ['length' => 255]);
        $events->addColumn('stream_version', 'integer');
        $events->addColumn('event_name', 'string', ['length' => 255]);
        $events->addColumn('schema_version', 'integer');
        $events->addColumn('payload', 'text');
        $events->addColumn('message_id', 'string', ['length' => 36]);
        $events->addColumn('message_timestamp', 'string', ['length' => 32]);
        $events->addColumn('message_meta', 'text');
        $events->setPrimaryKey(['global_position']);
        $events->addUniqueIndex(
            ['aggregate_name', 'aggregate_identifier', 'stream_version'],
            $this->tables->eventTable()->name() === 'event_store_events' ? 'event_store_stream_version_unique' : null
        );
        $events->addUniqueIndex(
            ['message_id'],
            $this->tables->eventTable()->name() === 'event_store_events' ? 'event_store_message_id_unique' : null
        );

        $position = $schema->createTable($this->tables->globalPositionTable()->schemaName());
        $position->addColumn('singleton', 'smallint');
        $position->addColumn('position', 'bigint');
        $position->setPrimaryKey(['singleton']);

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
