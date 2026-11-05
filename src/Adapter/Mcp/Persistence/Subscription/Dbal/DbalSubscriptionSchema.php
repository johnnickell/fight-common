<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use RuntimeException;

/**
 * Class DbalSubscriptionSchema
 */
final readonly class DbalSubscriptionSchema
{
    public const array TABLES = ['fight_mcp_subscription_state', 'fight_mcp_subscriptions'];

    /**
     * Adds bounded technical tracking through public DBAL schema APIs
     */
    public function addTo(Schema $schema, AbstractPlatform $platform): void
    {
        $stateTable = $schema->createTable(self::TABLES[0]);
        $stateTable->addColumn('singleton', 'integer');
        $stateTable->addColumn('version', 'integer');
        $stateTable->setPrimaryKey(['singleton']);

        $subscriptionTable = $schema->createTable(self::TABLES[1]);
        foreach (['registration_id', 'owner'] as $field) {
            $subscriptionTable->addColumn($field, 'string', ['length' => 32]);
        }

        $subscriptionTable->addColumn('context', 'text');
        foreach (['opened_at', 'observed_at', 'expires_at', 'closed_at'] as $field) {
            $subscriptionTable->addColumn($field, 'integer');
        }

        $subscriptionTable->setPrimaryKey(['registration_id']);
        $subscriptionTable->addIndex(['expires_at'], 'fight_mcp_subscription_expiry');
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
     * Creates or validates version-one tracking exclusively on an idle deployment connection
     */
    public function install(Connection $connection): void
    {
        if ($connection->isTransactionActive()) {
            throw new RuntimeException('Subscription installation requires an idle connection.');
        }

        $schemaManager = $connection->createSchemaManager();
        $present = array_intersect(self::TABLES, $schemaManager->listTableNames());
        if ($present !== []) {
            if (
                count($present) !== 2 || (int) $connection->fetchOne(
                    'SELECT version FROM fight_mcp_subscription_state WHERE singleton = 1'
                ) !== 1 || !$schemaManager->introspectTable(self::TABLES[1])->hasIndex('fight_mcp_subscription_expiry')
            ) {
                throw new RuntimeException('Unsupported subscription schema.');
            }

            return;
        }

        $schema = $schemaManager->introspectSchema();
        $this->addTo($schema, $connection->getDatabasePlatform());
        foreach (self::TABLES as $name) {
            $schemaManager->createTable($schema->getTable($name));
        }

        $connection->insert(self::TABLES[0], ['singleton' => 1, 'version' => 1]);
    }
}
