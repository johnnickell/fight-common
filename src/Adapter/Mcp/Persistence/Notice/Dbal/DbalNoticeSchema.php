<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use RuntimeException;

/**
 * Class DbalNoticeSchema
 */
final readonly class DbalNoticeSchema
{
    public const array TABLES = [
        'fight_notice_state', 'fight_notices', 'fight_notice_relationships', 'fight_notice_comparisons'
    ];

    /**
     * Adds a dedicated version-one namespace through DBAL public schema APIs
     */
    public function addTo(Schema $schema, AbstractPlatform $platform): void
    {
        $stateTable = $schema->createTable(self::TABLES[0]);
        $stateTable->addColumn('singleton', 'integer');
        $stateTable->addColumn('version', 'integer');
        $stateTable->addColumn('last_position', 'integer');
        $stateTable->addColumn('namespace', 'string', ['length' => 32]);
        $stateTable->addColumn('lost_through', 'integer');
        $stateTable->setPrimaryKey(['singleton']);

        $noticeTable = $schema->createTable(self::TABLES[1]);
        $noticeTable->addColumn('position', 'integer');
        $noticeTable->addColumn('scope', 'string', ['length' => 96]);
        $noticeTable->addColumn('producer', 'string', ['length' => 96]);
        $noticeTable->addColumn('notice_id', 'string', ['length' => 96]);
        $noticeTable->addColumn('recorded_at', 'string', ['length' => 27]);
        $noticeTable->addColumn('payload', 'text');
        $noticeTable->setPrimaryKey(['position']);
        $noticeTable->addUniqueIndex(['scope', 'producer', 'notice_id'], 'fight_notice_identity');
        $noticeTable->addIndex(['scope', 'position'], 'fight_notice_recovery');

        foreach ([self::TABLES[2], self::TABLES[3]] as $name) {
            $table = $schema->createTable($name);
            $table->addColumn('position', 'integer');
            $table->addColumn('payload', 'text');
            $table->setPrimaryKey(['position']);
            $table->addForeignKeyConstraint(self::TABLES[1], ['position'], ['position'], ['onDelete' => 'CASCADE']);
        }

        $schema->getTable(self::TABLES[3])->addColumn('version', 'integer');

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
     * Partial installations need operator recovery; MySQL DDL is not atomic
     */
    public function install(Connection $connection): void
    {
        if ($connection->isTransactionActive()) {
            throw new RuntimeException('Notice schema installation requires an idle connection.');
        }

        $schemaManager = $connection->createSchemaManager();
        $present = array_intersect(self::TABLES, $schemaManager->listTableNames());
        if ($present !== []) {
            if (count($present) !== count(self::TABLES)) {
                throw new RuntimeException('Incomplete notice schema.');
            }

            $version = $connection->fetchOne('SELECT version FROM fight_notice_state WHERE singleton = 1');
            $noticeTable = $schemaManager->introspectTable(self::TABLES[1]);
            if (
                (int) $version !== 1 || !$noticeTable->hasIndex('fight_notice_identity')
                || !$noticeTable->hasIndex('fight_notice_recovery')
                || !$schemaManager->introspectTable(self::TABLES[0])->hasColumn('namespace')
                || !$schemaManager->introspectTable(self::TABLES[0])->hasColumn('lost_through')
            ) {
                throw new RuntimeException('Unsupported notice schema.');
            }

            return;
        }

        $schema = $schemaManager->introspectSchema();
        $this->addTo($schema, $connection->getDatabasePlatform());
        foreach (self::TABLES as $name) {
            $schemaManager->createTable($schema->getTable($name));
        }

        $connection->insert(self::TABLES[0], [
            'singleton'     => 1,
            'version'       => 1,
            'last_position' => 0,
            'namespace'     => bin2hex(random_bytes(16)),
            'lost_through'  => 0
        ]);
    }
}
