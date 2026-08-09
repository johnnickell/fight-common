<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

/**
 * Portable Doctrine DBAL schema for durable projection checkpoints
 */
final readonly class DbalProjectionCheckpointStoreSchema
{
    private const string TABLE = 'projection_checkpoints';

    /**
     * Returns the projection-checkpoint schema definition
     */
    public function schema(): Schema
    {
        $schema = new Schema();
        $checkpoints = $schema->createTable(self::TABLE);
        $checkpoints->addColumn('projector_name', 'string', ['length' => 255]);
        $checkpoints->addColumn('global_position', 'bigint');
        $checkpoints->setPrimaryKey(['projector_name']);

        return $schema;
    }

    /**
     * Installs the projection-checkpoint schema independently and idempotently
     */
    public function install(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            $schemaManager->createTable($this->schema()->getTable(self::TABLE));
        }
    }
}
