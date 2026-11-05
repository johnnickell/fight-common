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
    private const string FAILURE_TABLE = 'publication_failures';
    private const string HANDLER_FAILURE_TABLE = 'publication_handler_failures';

    /**
     * Returns the publication-failure schema definition
     */
    public function schema(): Schema
    {
        $schema = new Schema();
        $failureTable = $schema->createTable(self::FAILURE_TABLE);
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

        $handlerFailureTable = $schema->createTable(self::HANDLER_FAILURE_TABLE);
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
            self::FAILURE_TABLE,
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

        if (!$schemaManager->tablesExist([self::FAILURE_TABLE])) {
            $schemaManager->createTable($schema->getTable(self::FAILURE_TABLE));
        }

        if (!$schemaManager->tablesExist([self::HANDLER_FAILURE_TABLE])) {
            $schemaManager->createTable($schema->getTable(self::HANDLER_FAILURE_TABLE));
        }
    }
}
