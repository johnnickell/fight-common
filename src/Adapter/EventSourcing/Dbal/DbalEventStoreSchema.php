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
    private const string EVENTS_TABLE = 'event_store_events';
    private const string GLOBAL_POSITION_TABLE = 'event_store_global_position';
    private const array TABLES = [
        self::EVENTS_TABLE,
        self::GLOBAL_POSITION_TABLE
    ];

    /**
     * Returns the event-store schema definition
     */
    public function schema(): Schema
    {
        $schema = new Schema();
        $events = $schema->createTable(self::EVENTS_TABLE);
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
            'event_store_stream_version_unique'
        );
        $events->addUniqueIndex(['message_id'], 'event_store_message_id_unique');

        $position = $schema->createTable(self::GLOBAL_POSITION_TABLE);
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

        foreach (self::TABLES as $table) {
            if (!$schemaManager->tablesExist([$table])) {
                $schemaManager->createTable($schema->getTable($table));
            }
        }

        if (
            false === $connection->fetchOne(
                sprintf('SELECT singleton FROM %s WHERE singleton = ?', self::GLOBAL_POSITION_TABLE),
                [1]
            )
        ) {
            $connection->insert(self::GLOBAL_POSITION_TABLE, [
                'singleton' => 1,
                'position'  => 0
            ]);
        }
    }
}
