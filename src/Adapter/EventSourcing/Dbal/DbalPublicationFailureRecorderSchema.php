<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

/**
 * Class DbalPublicationFailureRecorderSchema
 *
 * Portable Doctrine DBAL schema for durable publication-failure evidence
 */
final readonly class DbalPublicationFailureRecorderSchema
{
    /**
     * Constructs DbalPublicationFailureRecorderSchema
     */
    public function __construct(private DbalPublicationFailureTables $tables = new DbalPublicationFailureTables())
    {
    }

    /**
     * Returns the publication-failure schema definition
     */
    public function schema(): Schema
    {
        $schema = new Schema();
        $failureTable = $schema->createTable($this->tables->failureTable()->schemaName());
        $failureTable->addColumn('publication_name', 'string', ['length' => 255]);
        $failureTable->addColumn('aggregate_name', 'string', ['length' => 255]);
        $failureTable->addColumn('aggregate_identifier', 'string', ['length' => 1024]);
        $failureTable->addColumn('event_name', 'string', ['length' => 255]);
        $failureTable->addColumn('schema_version', 'integer');
        $failureTable->addColumn('stream_version', 'bigint');
        $failureTable->addColumn('global_position', 'bigint');
        $failureTable->addColumn('message_id', 'string', ['length' => 36]);
        $failureTable->addColumn('dispatch_started_at', 'string', ['length' => 32]);
        $failureTable->setPrimaryKey(['publication_name', 'global_position']);

        $handlerFailureTable = $schema->createTable($this->tables->handlerFailureTable()->schemaName());
        $handlerFailureTable->addColumn('publication_name', 'string', ['length' => 255]);
        $handlerFailureTable->addColumn('global_position', 'bigint');
        $handlerFailureTable->addColumn('handler_position', 'integer');
        $handlerFailureTable->addColumn('callable_description', 'text');
        $handlerFailureTable->addColumn('exception_class', 'string', ['length' => 1024]);
        $handlerFailureTable->addColumn('exception_code', 'bigint');
        $handlerFailureTable->addColumn('diagnostic_message', 'text');
        $handlerFailureTable->setPrimaryKey([
            'publication_name',
            'global_position',
            'handler_position'
        ]);
        $handlerFailureTable->addForeignKeyConstraint(
            $this->tables->failureTable()->schemaName(),
            ['publication_name', 'global_position'],
            ['publication_name', 'global_position'],
            ['onDelete' => 'CASCADE']
        );

        return $schema;
    }

    /**
     * Creates the publication-failure schema independently and idempotently
     */
    public function install(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $schema = $this->schema();

        // PostgreSQL reports case-sensitive names with DBAL quoting markers.
        $existingTables = array_map(
            static fn(string $tableName): string => trim($tableName, '`"'),
            $schemaManager->listTableNames()
        );

        foreach ([$this->tables->failureTable(), $this->tables->handlerFailureTable()] as $table) {
            if (!in_array($table->name(), $existingTables, true)) {
                $schemaManager->createTable($schema->getTable($table->name()));
            }
        }
    }
}
