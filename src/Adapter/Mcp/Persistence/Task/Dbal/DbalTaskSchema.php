<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use RuntimeException;

/**
 * Class DbalTaskSchema
 */
final readonly class DbalTaskSchema
{
    /**
     * Default table names only, not the configured storage selection
     *
     * @deprecated Planned v1.5.0; targeted for v2.0.0 removal after the released-minor window
     * Use DbalTaskTables accessors for selected names instead
     */
    public const array TABLES = ['fight_task_state', 'fight_tasks'];

    /**
     * Constructs DbalTaskSchema
     */
    public function __construct(private DbalTaskTables $tables = new DbalTaskTables())
    {
    }

    /**
     * Adds the dedicated version-one lifecycle namespace through public DBAL 4.4 APIs
     */
    public function addTo(Schema $schema, AbstractPlatform $platform): void
    {
        $stateTable = $schema->createTable($this->tables->stateTable()->schemaName());
        $stateTable->addColumn('singleton', 'integer');
        $stateTable->addColumn('version', 'integer');
        $stateTable->setPrimaryKey(['singleton']);

        $taskTable = $schema->createTable($this->tables->taskTable()->schemaName());
        $taskTable->addColumn('scope', 'string', ['length' => 96]);
        $taskTable->addColumn('task_id', 'string', ['length' => 32]);
        $taskTable->addColumn('payload', 'text', ['length' => 16777215]);
        $taskTable->setPrimaryKey(['scope', 'task_id']);
        if ($platform instanceof AbstractMySQLPlatform) {
            foreach ($this->selectedTables() as $name) {
                $table = $schema->getTable($name->schemaName());
                $table->addOption('engine', 'InnoDB');
                $table->addOption('charset', 'utf8mb4');
                $table->addOption('collation', 'utf8mb4_bin');
            }
        }
    }

    /**
     * Creates or validates the current namespace exclusively on an idle deployment connection
     *
     * Partial installation requires operator recovery because MySQL DDL is not transactional
     */
    public function install(Connection $connection): void
    {
        if ($connection->isTransactionActive()) {
            throw new RuntimeException('Task schema installation requires an idle connection.');
        }

        $schemaManager = $connection->createSchemaManager();
        // PostgreSQL reports case-sensitive names with DBAL quoting markers.
        $existingTables = array_map(
            static fn(string $tableName): string => trim($tableName, '`"'),
            $schemaManager->listTableNames()
        );
        $selectedNames = array_map(static fn(DbalTableName $table): string => $table->name(), $this->selectedTables());
        $present = array_intersect($selectedNames, $existingTables);
        if ($present !== []) {
            if (count($present) !== count($selectedNames)) {
                throw new RuntimeException('Incomplete Task schema.');
            }

            if (
                (int) $connection->fetchOne(
                    sprintf(
                        'SELECT version FROM %s WHERE singleton = 1',
                        $this->tables->stateTable()->quoted($connection->getDatabasePlatform())
                    )
                ) !== 1
                || !$schemaManager->introspectTable($this->tables->taskTable()->schemaName())->hasColumn('payload')
            ) {
                throw new RuntimeException('Unsupported Task schema.');
            }

            return;
        }

        $schema = $schemaManager->introspectSchema();
        $this->addTo($schema, $connection->getDatabasePlatform());
        foreach ($this->selectedTables() as $name) {
            $schemaManager->createTable($schema->getTable($name->schemaName()));
        }

        $connection->insert(
            $this->tables->stateTable()->quoted($connection->getDatabasePlatform()),
            ['singleton' => 1, 'version' => 1]
        );
    }

    /**
     * Returns the selected namespace tables in installation order
     *
     * @return list<DbalTableName>
     */
    private function selectedTables(): array
    {
        return [$this->tables->stateTable(), $this->tables->taskTable()];
    }
}
