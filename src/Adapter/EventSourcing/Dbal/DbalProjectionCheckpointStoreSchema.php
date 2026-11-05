<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;

/**
 * Class DbalProjectionCheckpointStoreSchema
 *
 * Portable Doctrine DBAL schema for durable projection checkpoints
 */
final readonly class DbalProjectionCheckpointStoreSchema
{
    /**
     * Constructs DbalProjectionCheckpointStoreSchema
     */
    public function __construct(private DbalTableName $table = new DbalTableName('projection_checkpoints'))
    {
    }

    /**
     * Returns the projection-checkpoint schema definition
     */
    public function schema(): Schema
    {
        $schema = new Schema();
        $checkpoints = $schema->createTable($this->table->schemaName());
        $checkpoints->addColumn('projector_name', 'string', ['length' => 255]);
        $checkpoints->addColumn('global_position', 'bigint');
        $checkpoints->setPrimaryKey(['projector_name']);

        return $schema;
    }

    /**
     * Creates the projection-checkpoint schema independently and idempotently
     */
    public function install(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();

        // PostgreSQL reports case-sensitive names with DBAL quoting markers.
        $existingTables = array_map(
            static fn(string $tableName): string => trim($tableName, '`"'),
            $schemaManager->listTableNames()
        );

        if (!in_array($this->table->name(), $existingTables, true)) {
            $schemaManager->createTable($this->schema()->getTable($this->table->name()));
        }
    }
}
