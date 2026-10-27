<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use RuntimeException;

/**
 * Class DbalTaskSchema
 */
final readonly class DbalTaskSchema
{
    public const array TABLES = ['fight_task_state', 'fight_tasks'];

    /**
     * Adds the dedicated version-one lifecycle namespace through public DBAL 4.4 APIs
     */
    public function addTo(Schema $schema, AbstractPlatform $platform): void
    {
        $state = $schema->createTable(self::TABLES[0]);
        $state->addColumn('singleton', 'integer');
        $state->addColumn('version', 'integer');
        $state->setPrimaryKey(['singleton']);

        $tasks = $schema->createTable(self::TABLES[1]);
        $tasks->addColumn('scope', 'string', ['length' => 96]);
        $tasks->addColumn('task_id', 'string', ['length' => 32]);
        $tasks->addColumn('payload', 'text', ['length' => 16777215]);
        $tasks->setPrimaryKey(['scope', 'task_id']);
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
     * Creates or validates the current namespace exclusively on an idle deployment connection
     *
     * Partial installation requires operator recovery because MySQL DDL is not transactional
     */
    public function install(Connection $connection): void
    {
        if ($connection->isTransactionActive()) {
            throw new RuntimeException('Task schema installation requires an idle connection.');
        }

        $manager = $connection->createSchemaManager();
        $present = array_intersect(self::TABLES, $manager->listTableNames());
        if ($present !== []) {
            if (count($present) !== count(self::TABLES)) {
                throw new RuntimeException('Incomplete Task schema.');
            }

            if (
                (int) $connection->fetchOne('SELECT version FROM fight_task_state WHERE singleton = 1') !== 1
                || !$manager->introspectTable(self::TABLES[1])->hasColumn('payload')
            ) {
                throw new RuntimeException('Unsupported Task schema.');
            }

            return;
        }

        $schema = $manager->introspectSchema();
        $this->addTo($schema, $connection->getDatabasePlatform());
        foreach (self::TABLES as $name) {
            $manager->createTable($schema->getTable($name));
        }

        $connection->insert(self::TABLES[0], ['singleton' => 1, 'version' => 1]);
    }
}
