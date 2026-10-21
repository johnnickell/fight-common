<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Execution\Dbal;

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
        'fight_execution_observations'
    ];

    /**
     * Adds version-two tables to a consumer-owned schema without changing other tables
     */
    public function addTo(Schema $schema, AbstractPlatform $platform): void
    {
        $version = $schema->createTable(self::TABLES[0]);
        $version->addColumn('singleton', 'integer');
        $version->addColumn('version', 'integer');
        $version->setPrimaryKey(['singleton']);

        $sessions = $schema->createTable(self::TABLES[1]);
        $sessions->addColumn('project_id', 'string', ['length' => 128]);
        $sessions->addColumn('session_id', 'string', ['length' => 128]);
        $sessions->addColumn('consumer_reference', 'string', ['length' => 128]);
        $sessions->setPrimaryKey(['project_id', 'session_id']);

        $phases = $schema->createTable(self::TABLES[2]);
        $phases->addColumn('project_id', 'string', ['length' => 128]);
        $phases->addColumn('session_id', 'string', ['length' => 128]);
        $phases->addColumn('phase_id', 'string', ['length' => 128]);
        $phases->addColumn('parent_phase_id', 'string', ['length' => 128, 'notnull' => false]);
        $phases->addColumn('agent_id', 'string', ['length' => 128]);
        $phases->addColumn('name', 'string', ['length' => 64]);
        $phases->addColumn('attempt', 'integer');
        $phases->setPrimaryKey(['project_id', 'phase_id']);
        $phases->addUniqueIndex(['project_id', 'session_id', 'phase_id'], 'fight_execution_phase_session');
        $phases->addForeignKeyConstraint(
            self::TABLES[1],
            ['project_id', 'session_id'],
            ['project_id', 'session_id']
        );
        $phases->addForeignKeyConstraint(
            self::TABLES[2],
            ['project_id', 'session_id', 'parent_phase_id'],
            ['project_id', 'session_id', 'phase_id']
        );

        $observations = $schema->createTable(self::TABLES[3]);
        $observations->addColumn('producer_id', 'string', ['length' => 128]);
        $observations->addColumn('observation_id', 'string', ['length' => 36]);
        $observations->addColumn('project_id', 'string', ['length' => 128]);
        $observations->addColumn('session_id', 'string', ['length' => 128]);
        $observations->addColumn('phase_id', 'string', ['length' => 128, 'notnull' => false]);
        $observations->addColumn('version', 'integer');
        $observations->addColumn('payload', 'text');
        $observations->addColumn('recorded_at', 'string', ['length' => 27]);
        $observations->setPrimaryKey(['producer_id', 'observation_id']);
        $observations->addIndex(['recorded_at', 'observation_id'], 'fight_execution_recording_order');
        $observations->addForeignKeyConstraint(
            self::TABLES[1],
            ['project_id', 'session_id'],
            ['project_id', 'session_id']
        );
        $observations->addForeignKeyConstraint(
            self::TABLES[2],
            ['project_id', 'session_id', 'phase_id'],
            ['project_id', 'session_id', 'phase_id']
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
     * Creates an empty namespace or evolves version one without rewriting evidence
     *
     * Run exclusively during deployment, never during a recording command. Partial
     * installations require operator recovery; MySQL DDL is not transactional.
     */
    public function install(Connection $connection): void
    {
        if ($connection->isTransactionActive()) {
            throw new RuntimeException('Observation schema installation requires an idle connection.');
        }

        $manager = $connection->createSchemaManager();
        $present = array_intersect(self::TABLES, $manager->listTableNames());
        if ($present !== []) {
            if (
                count($present) !== count(self::TABLES)
                || !in_array((int) $connection->fetchOne(
                    'SELECT version FROM fight_execution_schema WHERE singleton = 1'
                ), [1, 2], true)
            ) {
                throw new RuntimeException('Unsupported or incomplete observation schema.');
            }

            $table = $manager->introspectTable(self::TABLES[3]);
            $version = (int) $connection->fetchOne('SELECT version FROM fight_execution_schema WHERE singleton = 1');
            if ($version === 1) {
                if (!$table->hasIndex('fight_execution_recording_order')) {
                    $table->addIndex(['recorded_at', 'observation_id'], 'fight_execution_recording_order');
                    $manager->createIndex($table->getIndex('fight_execution_recording_order'), self::TABLES[3]);
                }

                $connection->update(self::TABLES[0], ['version' => 2], ['singleton' => 1]);
            } elseif (!$table->hasIndex('fight_execution_recording_order')) {
                throw new RuntimeException('Incomplete observation schema version two.');
            }

            return;
        }

        $schema = $manager->introspectSchema();
        $this->addTo($schema, $connection->getDatabasePlatform());
        foreach (self::TABLES as $table) {
            $manager->createTable($schema->getTable($table));
        }

        $connection->insert(self::TABLES[0], ['singleton' => 1, 'version' => 2]);
    }
}
