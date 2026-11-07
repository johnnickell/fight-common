<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use RuntimeException;

/**
 * Class DbalNoticeSchema
 */
final readonly class DbalNoticeSchema
{
    /**
     * Default table names only, not the configured storage selection
     *
     * @deprecated Planned v1.5.0; targeted for v2.0.0 removal after the released-minor window
     * Use DbalNoticeTables accessors for selected names instead
     */
    public const array TABLES = [
        'fight_notice_state', 'fight_notices', 'fight_notice_relationships', 'fight_notice_comparisons'
    ];

    /**
     * Constructs DbalNoticeSchema
     */
    public function __construct(private DbalNoticeTables $tables = new DbalNoticeTables())
    {
    }

    /**
     * Adds a dedicated version-one namespace through DBAL public schema APIs
     */
    public function addTo(Schema $schema, AbstractPlatform $platform): void
    {
        $stateTable = $schema->createTable($this->tables->stateTable()->schemaName());
        $stateTable->addColumn('singleton', 'integer');
        $stateTable->addColumn('version', 'integer');
        $stateTable->addColumn('last_position', 'integer');
        $stateTable->addColumn('namespace', 'string', ['length' => 32]);
        $stateTable->addColumn('lost_through', 'integer');
        $stateTable->setPrimaryKey(['singleton']);

        $noticeTable = $schema->createTable($this->tables->noticeTable()->schemaName());
        $noticeTable->addColumn('position', 'integer');
        $noticeTable->addColumn('scope', 'string', ['length' => 96]);
        $noticeTable->addColumn('producer', 'string', ['length' => 96]);
        $noticeTable->addColumn('notice_id', 'string', ['length' => 96]);
        $noticeTable->addColumn('recorded_at', 'string', ['length' => 27]);
        $noticeTable->addColumn('payload', 'text');
        $noticeTable->setPrimaryKey(['position']);
        $noticeTable->addUniqueIndex(['scope', 'producer', 'notice_id'], $this->indexName('fight_notice_identity'));
        $noticeTable->addIndex(['scope', 'position'], $this->indexName('fight_notice_recovery'));

        foreach ([$this->tables->relationshipTable(), $this->tables->comparisonTable()] as $name) {
            $table = $schema->createTable($name->schemaName());
            $table->addColumn('position', 'integer');
            $table->addColumn('payload', 'text');
            $table->setPrimaryKey(['position']);
            $table->addForeignKeyConstraint(
                $this->tables->noticeTable()->schemaName(),
                ['position'],
                ['position'],
                ['onDelete' => 'CASCADE']
            );
        }

        $schema->getTable($this->tables->comparisonTable()->schemaName())->addColumn('version', 'integer');

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
     * Partial installations need operator recovery; MySQL DDL is not atomic
     */
    public function install(Connection $connection): void
    {
        if ($connection->isTransactionActive()) {
            throw new RuntimeException('Notice schema installation requires an idle connection.');
        }

        $schemaManager = $connection->createSchemaManager();
        $existingTables = array_map(
            static fn(string $tableName): string => trim($tableName, '`"'),
            $schemaManager->listTableNames()
        );
        $selectedNames = array_map(static fn(DbalTableName $table): string => $table->name(), $this->selectedTables());
        $present = array_intersect($selectedNames, $existingTables);
        if ($present !== []) {
            if (count($present) !== count($selectedNames)) {
                throw new RuntimeException('Incomplete notice schema.');
            }

            $version = $connection->fetchOne(
                sprintf(
                    'SELECT version FROM %s WHERE singleton = 1',
                    $this->tables->stateTable()->quoted($connection->getDatabasePlatform())
                )
            );
            $noticeTable = $schemaManager->introspectTable($this->tables->noticeTable()->schemaName());
            $stateTable = $schemaManager->introspectTable($this->tables->stateTable()->schemaName());
            $relationshipTable = $schemaManager->introspectTable($this->tables->relationshipTable()->schemaName());
            $comparisonTable = $schemaManager->introspectTable($this->tables->comparisonTable()->schemaName());
            if (
                (int) $version !== 1 || !$noticeTable->hasIndex($this->indexName('fight_notice_identity'))
                || !$noticeTable->hasIndex($this->indexName('fight_notice_recovery'))
                || !$stateTable->hasColumn('namespace') || !$stateTable->hasColumn('lost_through')
                || !$comparisonTable->hasColumn('version')
                || !$this->referencesSelectedNotices($relationshipTable)
                || !$this->referencesSelectedNotices($comparisonTable)
            ) {
                throw new RuntimeException('Unsupported notice schema.');
            }

            return;
        }

        $schema = $schemaManager->introspectSchema();
        $this->addTo($schema, $connection->getDatabasePlatform());
        foreach ($this->selectedTables() as $name) {
            $schemaManager->createTable($schema->getTable($name->schemaName()));
        }

        $connection->insert($this->tables->stateTable()->quoted($connection->getDatabasePlatform()), [
            'singleton'     => 1,
            'version'       => 1,
            'last_position' => 0,
            'namespace'     => bin2hex(random_bytes(16)),
            'lost_through'  => 0
        ]);
    }

    /**
     * Returns the selected tables in installation order
     *
     * @return list<DbalTableName>
     */
    private function selectedTables(): array
    {
        return [
            $this->tables->stateTable(), $this->tables->noticeTable(),
            $this->tables->relationshipTable(), $this->tables->comparisonTable()
        ];
    }

    /**
     * Returns a stable index name without conflicting with a default installation
     */
    private function indexName(string $defaultIndex): string
    {
        if ($this->tables->noticeTable()->name() === self::TABLES[1]) {
            return $defaultIndex;
        }

        return $defaultIndex.'_'.substr(hash('sha256', $this->tables->noticeTable()->name()), 0, 12);
    }

    /**
     * Validates the selected parent and cascading whole-Notice relationship
     */
    private function referencesSelectedNotices(Table $table): bool
    {
        foreach ($table->getForeignKeys() as $foreignKey) {
            $localColumns = array_map(
                static fn(string $name): string => trim($name, '`"'),
                $foreignKey->getLocalColumns()
            );
            $foreignColumns = array_map(
                static fn(string $name): string => trim($name, '`"'),
                $foreignKey->getForeignColumns()
            );
            if (
                trim($foreignKey->getForeignTableName(), '`"') === $this->tables->noticeTable()->name()
                && $localColumns === ['position'] && $foreignColumns === ['position']
                && strtoupper($foreignKey->onDelete() ?? '') === 'CASCADE'
            ) {
                return true;
            }
        }

        return false;
    }
}
