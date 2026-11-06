<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;

/**
 * Class DbalPublicationCursorStoreSchema
 *
 * Portable Doctrine DBAL schema for durable event-publication cursors
 */
final readonly class DbalPublicationCursorStoreSchema
{
    /**
     * Constructs DbalPublicationCursorStoreSchema
     */
    public function __construct(private DbalTableName $table = new DbalTableName('publication_cursors'))
    {
    }

    /**
     * Returns the publication-cursor schema definition
     */
    public function schema(): Schema
    {
        $schema = new Schema();
        $cursorTable = $schema->createTable($this->table->schemaName());
        $cursorTable->addColumn('publication_name', 'string', ['length' => 255]);
        $cursorTable->addColumn('global_position', 'bigint');
        $cursorTable->setPrimaryKey(['publication_name']);

        return $schema;
    }

    /**
     * Creates the publication-cursor schema independently and idempotently
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
