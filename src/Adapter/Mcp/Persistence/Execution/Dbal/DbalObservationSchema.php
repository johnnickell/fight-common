<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
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
     * Adds version-one tables to a consumer-owned schema without changing other tables
     */
    public function addTo(Schema $schema, AbstractPlatform $platform): void
    {
        $schemaVersionTable = $schema->createTable(self::TABLES[0]);
        $schemaVersionTable->addColumn('singleton', 'integer');
        $schemaVersionTable->addColumn('version', 'integer');
        $schemaVersionTable->setPrimaryKey(['singleton']);

        $sessionTable = $schema->createTable(self::TABLES[1]);
        $sessionTable->addColumn('project_id', 'string', ['length' => 128]);
        $sessionTable->addColumn('session_id', 'string', ['length' => 128]);
        $sessionTable->addColumn('consumer_reference', 'string', ['length' => 128]);
        $sessionTable->setPrimaryKey(['project_id', 'session_id']);

        $phaseTable = $schema->createTable(self::TABLES[2]);
        $phaseTable->addColumn('project_id', 'string', ['length' => 128]);
        $phaseTable->addColumn('session_id', 'string', ['length' => 128]);
        $phaseTable->addColumn('phase_id', 'string', ['length' => 128]);
        $phaseTable->addColumn('parent_phase_id', 'string', ['length' => 128, 'notnull' => false]);
        $phaseTable->addColumn('agent_id', 'string', ['length' => 128]);
        $phaseTable->addColumn('name', 'string', ['length' => 64]);
        $phaseTable->addColumn('attempt', 'integer');
        $phaseTable->setPrimaryKey(['project_id', 'phase_id']);
        $phaseTable->addUniqueIndex(['project_id', 'session_id', 'phase_id'], 'fight_execution_phase_session');
        $phaseTable->addForeignKeyConstraint(
            self::TABLES[1],
            ['project_id', 'session_id'],
            ['project_id', 'session_id']
        );
        $phaseTable->addForeignKeyConstraint(
            self::TABLES[2],
            ['project_id', 'session_id', 'parent_phase_id'],
            ['project_id', 'session_id', 'phase_id']
        );

        $observationTable = $schema->createTable(self::TABLES[3]);
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
        $observationTable->addIndex(['recorded_at', 'observation_id'], 'fight_execution_recording_order');
        $observationTable->addForeignKeyConstraint(
            self::TABLES[1],
            ['project_id', 'session_id'],
            ['project_id', 'session_id']
        );
        $observationTable->addForeignKeyConstraint(
            self::TABLES[2],
            ['project_id', 'session_id', 'phase_id'],
            ['project_id', 'session_id', 'phase_id']
        );
        $observationTable->addForeignKeyConstraint(
            self::TABLES[3],
            ['producer_id', 'activity_id'],
            ['producer_id', 'observation_id']
        );

        $protectedContentTable = $schema->createTable(self::TABLES[4]);
        $protectedContentTable->addColumn('producer_id', 'string', ['length' => 128]);
        $protectedContentTable->addColumn('observation_id', 'string', ['length' => 36]);
        // JSON escaping may expand a bounded body beyond MySQL's ordinary TEXT limit.
        $protectedContentTable->addColumn('payload', 'text', ['length' => 16777215]);
        $protectedContentTable->setPrimaryKey(['producer_id', 'observation_id']);
        $protectedContentTable->addForeignKeyConstraint(
            self::TABLES[3],
            ['producer_id', 'observation_id'],
            ['producer_id', 'observation_id'],
            ['onDelete' => 'CASCADE']
        );

        if ($platform instanceof AbstractMySQLPlatform) {
            foreach (self::TABLES as $name) {
                $table = $schema->getTable($name);
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
        $present = array_intersect(self::TABLES, $schemaManager->listTableNames());
        if ($present !== []) {
            if (
                count($present) !== count(self::TABLES)
                || (int) $connection->fetchOne(
                    'SELECT version FROM fight_execution_schema WHERE singleton = 1'
                ) !== 1
            ) {
                throw new RuntimeException('Unsupported or incomplete observation schema.');
            }

            $observationTable = $schemaManager->introspectTable(self::TABLES[3]);
            if (
                !$observationTable->hasIndex('fight_execution_recording_order')
                || !$observationTable->hasColumn('activity_id')
            ) {
                throw new RuntimeException('Incomplete observation schema version one.');
            }

            return;
        }

        $schema = $schemaManager->introspectSchema();
        $this->addTo($schema, $connection->getDatabasePlatform());
        foreach (self::TABLES as $tableName) {
            $schemaManager->createTable($schema->getTable($tableName));
        }

        $connection->insert(self::TABLES[0], ['singleton' => 1, 'version' => 1]);
    }
}
