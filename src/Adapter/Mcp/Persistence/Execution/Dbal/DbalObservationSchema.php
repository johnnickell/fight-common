<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use RuntimeException;

/**
 * Class DbalObservationSchema
 */
final readonly class DbalObservationSchema
{
    public const array TABLES = [
        'fight_execution_schema',
        'fight_execution_sessions',
        'fight_execution_phases',
        'fight_execution_observations',
        'fight_execution_contents'
    ];

    /**
     * Constructs DbalObservationSchema
     */
    public function __construct(private DbalObservationTables $tables = new DbalObservationTables())
    {
    }

    /**
     * Adds version-one tables to a consumer-owned schema without changing other tables
     */
    public function addTo(Schema $schema, AbstractPlatform $platform): void
    {
        $schemaVersionTable = $schema->createTable($this->tables->schemaTable()->schemaName());
        $schemaVersionTable->addColumn('singleton', 'integer');
        $schemaVersionTable->addColumn('version', 'integer');
        $schemaVersionTable->setPrimaryKey(['singleton']);

        $sessionTable = $schema->createTable($this->tables->sessionTable()->schemaName());
        $sessionTable->addColumn('project_id', 'string', ['length' => 128]);
        $sessionTable->addColumn('session_id', 'string', ['length' => 128]);
        $sessionTable->addColumn('consumer_reference', 'string', ['length' => 128]);
        $sessionTable->setPrimaryKey(['project_id', 'session_id']);

        $phaseTable = $schema->createTable($this->tables->phaseTable()->schemaName());
        $phaseTable->addColumn('project_id', 'string', ['length' => 128]);
        $phaseTable->addColumn('session_id', 'string', ['length' => 128]);
        $phaseTable->addColumn('phase_id', 'string', ['length' => 128]);
        $phaseTable->addColumn('parent_phase_id', 'string', ['length' => 128, 'notnull' => false]);
        $phaseTable->addColumn('agent_id', 'string', ['length' => 128]);
        $phaseTable->addColumn('name', 'string', ['length' => 64]);
        $phaseTable->addColumn('attempt', 'integer');
        $phaseTable->setPrimaryKey(['project_id', 'phase_id']);
        $phaseTable->addUniqueIndex(
            ['project_id', 'session_id', 'phase_id'],
            $this->indexName($this->tables->phaseTable(), self::TABLES[2], 'fight_execution_phase_session')
        );
        $phaseTable->addForeignKeyConstraint(
            $this->tables->sessionTable()->schemaName(),
            ['project_id', 'session_id'],
            ['project_id', 'session_id']
        );
        $phaseTable->addForeignKeyConstraint(
            $this->tables->phaseTable()->schemaName(),
            ['project_id', 'session_id', 'parent_phase_id'],
            ['project_id', 'session_id', 'phase_id']
        );

        $observationTable = $schema->createTable($this->tables->observationTable()->schemaName());
        $observationTable->addColumn('producer_id', 'string', ['length' => 128]);
        $observationTable->addColumn('observation_id', 'string', ['length' => 36]);
        $observationTable->addColumn('project_id', 'string', ['length' => 128]);
        $observationTable->addColumn('session_id', 'string', ['length' => 128]);
        $observationTable->addColumn('phase_id', 'string', ['length' => 128, 'notnull' => false]);
        $observationTable->addColumn('activity_id', 'string', ['length' => 36, 'notnull' => false]);
        $observationTable->addColumn('version', 'integer');
        $observationTable->addColumn('payload', 'text');
        $observationTable->addColumn('recorded_at', 'string', ['length' => 27]);
        $observationTable->setPrimaryKey(['producer_id', 'observation_id']);
        $observationTable->addIndex(
            ['recorded_at', 'observation_id'],
            $this->indexName($this->tables->observationTable(), self::TABLES[3], 'fight_execution_recording_order')
        );
        $observationTable->addForeignKeyConstraint(
            $this->tables->sessionTable()->schemaName(),
            ['project_id', 'session_id'],
            ['project_id', 'session_id']
        );
        $observationTable->addForeignKeyConstraint(
            $this->tables->phaseTable()->schemaName(),
            ['project_id', 'session_id', 'phase_id'],
            ['project_id', 'session_id', 'phase_id']
        );
        $observationTable->addForeignKeyConstraint(
            $this->tables->observationTable()->schemaName(),
            ['producer_id', 'activity_id'],
            ['producer_id', 'observation_id']
        );

        $protectedContentTable = $schema->createTable($this->tables->contentTable()->schemaName());
        $protectedContentTable->addColumn('producer_id', 'string', ['length' => 128]);
        $protectedContentTable->addColumn('observation_id', 'string', ['length' => 36]);
        // JSON escaping may expand a bounded body beyond MySQL's ordinary TEXT limit.
        $protectedContentTable->addColumn('payload', 'text', ['length' => 16777215]);
        $protectedContentTable->setPrimaryKey(['producer_id', 'observation_id']);
        $protectedContentTable->addForeignKeyConstraint(
            $this->tables->observationTable()->schemaName(),
            ['producer_id', 'observation_id'],
            ['producer_id', 'observation_id'],
            ['onDelete' => 'CASCADE']
        );

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
     * Creates an empty version-one namespace or validates the current installation
     *
     * Run exclusively during deployment, never during a recording command. Partial
     * installations require operator recovery; MySQL DDL is not transactional.
     */
    public function install(Connection $connection): void
    {
        if ($connection->isTransactionActive()) {
            throw new RuntimeException('Observation schema installation requires an idle connection.');
        }

        $schemaManager = $connection->createSchemaManager();
        $existingTables = array_map(
            static fn(string $tableName): string => trim($tableName, '`"'),
            $schemaManager->listTableNames()
        );
        $selectedNames = array_map(static fn(DbalTableName $table): string => $table->name(), $this->selectedTables());
        $present = array_intersect($selectedNames, $existingTables);
        if ($present !== []) {
            if (
                count($present) !== count($selectedNames)
                || (int) $connection->fetchOne(
                    sprintf(
                        'SELECT version FROM %s WHERE singleton = 1',
                        $this->tables->schemaTable()->quoted($connection->getDatabasePlatform())
                    )
                ) !== 1
            ) {
                throw new RuntimeException('Unsupported or incomplete observation schema.');
            }

            $observationTable = $schemaManager->introspectTable($this->tables->observationTable()->schemaName());
            $indexName = $this->indexName(
                $this->tables->observationTable(),
                self::TABLES[3],
                'fight_execution_recording_order'
            );
            if (!$observationTable->hasIndex($indexName) || !$observationTable->hasColumn('activity_id')) {
                throw new RuntimeException('Incomplete observation schema version one.');
            }

            return;
        }

        $schema = $schemaManager->introspectSchema();
        $this->addTo($schema, $connection->getDatabasePlatform());
        foreach ($this->selectedTables() as $tableName) {
            $schemaManager->createTable($schema->getTable($tableName->schemaName()));
        }

        $connection->insert(
            $this->tables->schemaTable()->quoted($connection->getDatabasePlatform()),
            ['singleton' => 1, 'version' => 1]
        );
    }

    /**
     * Returns the selected tables in dependency order
     *
     * @phpstan-return list<DbalTableName>
     */
    private function selectedTables(): array
    {
        return [
            $this->tables->schemaTable(),
            $this->tables->sessionTable(),
            $this->tables->phaseTable(),
            $this->tables->observationTable(),
            $this->tables->contentTable()
        ];
    }

    /**
     * Returns a stable index name without conflicting with a default installation
     */
    private function indexName(DbalTableName $table, string $defaultTable, string $defaultIndex): string
    {
        if ($table->name() === $defaultTable) {
            return $defaultIndex;
        }

        return $defaultIndex.'_'.substr(hash('sha256', $table->name()), 0, 12);
    }
}
