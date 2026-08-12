<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

/**
 * Class DbalPublicationCursorStoreSchema
 *
 * Portable Doctrine DBAL schema for durable event-publication cursors
 */
final readonly class DbalPublicationCursorStoreSchema
{
    private const string TABLE = 'publication_cursors';

    /**
     * Returns the publication-cursor schema definition
     */
    public function schema(): Schema
    {
        $schema = new Schema();
        $cursors = $schema->createTable(self::TABLE);
        $cursors->addColumn('publication_name', 'string', ['length' => 255]);
        $cursors->addColumn('global_position', 'bigint');
        $cursors->setPrimaryKey(['publication_name']);

        return $schema;
    }

    /**
     * Creates the publication-cursor schema independently and idempotently
     */
    public function install(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            $schemaManager->createTable($this->schema()->getTable(self::TABLE));
        }
    }
}
