<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use RuntimeException;

/**
 * Class DbalSubscriptionSchema
 */
final readonly class DbalSubscriptionSchema
{
    public const array TABLES = ['fight_mcp_subscription_state', 'fight_mcp_subscriptions'];

    /**
     * Constructs DbalSubscriptionSchema
     */
    public function __construct(private DbalSubscriptionTables $tables = new DbalSubscriptionTables())
    {
    }

    /**
     * Adds bounded technical tracking through public DBAL schema APIs
     */
    public function addTo(Schema $schema, AbstractPlatform $platform): void
    {
        $stateTable = $schema->createTable($this->tables->stateTable()->schemaName());
        $stateTable->addColumn('singleton', 'integer');
        $stateTable->addColumn('version', 'integer');
        $stateTable->setPrimaryKey(['singleton']);

        $subscriptionTable = $schema->createTable($this->tables->subscriptionTable()->schemaName());
        foreach (['registration_id', 'owner'] as $field) {
            $subscriptionTable->addColumn($field, 'string', ['length' => 32]);
        }

        $subscriptionTable->addColumn('context', 'text');
        foreach (['opened_at', 'observed_at', 'expires_at', 'closed_at'] as $field) {
            $subscriptionTable->addColumn($field, 'integer');
        }

        $subscriptionTable->setPrimaryKey(['registration_id']);
        $subscriptionTable->addIndex(['expires_at'], $this->expiryIndex());
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
     * Creates or validates version-one tracking exclusively on an idle deployment connection
     */
    public function install(Connection $connection): void
    {
        if ($connection->isTransactionActive()) {
            throw new RuntimeException('Subscription installation requires an idle connection.');
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
            if (
                count($present) !== count($selectedNames) || (int) $connection->fetchOne(
                    sprintf(
                        'SELECT version FROM %s WHERE singleton = 1',
                        $this->tables->stateTable()->quoted($connection->getDatabasePlatform())
                    )
                ) !== 1 || !$schemaManager->introspectTable($this->tables->subscriptionTable()->schemaName())
                    ->hasIndex($this->expiryIndex())
            ) {
                throw new RuntimeException('Unsupported subscription schema.');
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
        return [$this->tables->stateTable(), $this->tables->subscriptionTable()];
    }

    /**
     * Returns a coexistence-safe index name while preserving the default schema
     */
    private function expiryIndex(): string
    {
        $tableName = $this->tables->subscriptionTable()->name();
        if ($tableName === self::TABLES[1]) {
            return 'fight_mcp_subscription_expiry';
        }

        return 'mcp_subscription_expiry_'.substr(hash('sha256', $tableName), 0, 16);
    }
}
