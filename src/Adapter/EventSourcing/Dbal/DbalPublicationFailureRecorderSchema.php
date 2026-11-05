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
        $failures = $schema->createTable($this->tables->failureTable()->schemaName());
        $failures->addColumn('publication_name', 'string', ['length' => 255]);
        $failures->addColumn('aggregate_name', 'string', ['length' => 255]);
        $failures->addColumn('aggregate_identifier', 'string', ['length' => 1024]);
        $failures->addColumn('event_name', 'string', ['length' => 255]);
        $failures->addColumn('schema_version', 'integer');
        $failures->addColumn('stream_version', 'bigint');
        $failures->addColumn('global_position', 'bigint');
        $failures->addColumn('message_id', 'string', ['length' => 36]);
        $failures->addColumn('dispatch_started_at', 'string', ['length' => 32]);
        $failures->setPrimaryKey(['publication_name', 'global_position']);

        $handlerFailures = $schema->createTable($this->tables->handlerFailureTable()->schemaName());
        $handlerFailures->addColumn('publication_name', 'string', ['length' => 255]);
        $handlerFailures->addColumn('global_position', 'bigint');
        $handlerFailures->addColumn('handler_position', 'integer');
        $handlerFailures->addColumn('callable_description', 'text');
        $handlerFailures->addColumn('exception_class', 'string', ['length' => 1024]);
        $handlerFailures->addColumn('exception_code', 'bigint');
        $handlerFailures->addColumn('diagnostic_message', 'text');
        $handlerFailures->setPrimaryKey([
            'publication_name',
            'global_position',
            'handler_position'
        ]);
        $handlerFailures->addForeignKeyConstraint(
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
