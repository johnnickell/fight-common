<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreSchema;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreTables;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalEventStoreSchema::class)]
final class DbalEventStoreSchemaUnitTest extends UnitTestCase
{
    public function test_that_schema_defines_the_event_and_global_position_tables(): void
    {
        $schema = (new DbalEventStoreSchema())->schema();

        self::assertTrue($schema->hasTable('event_store_events'));
        self::assertTrue($schema->hasTable('event_store_global_position'));
        self::assertSame(['global_position'], $schema->getTable('event_store_events')->getPrimaryKey()->getColumns());
        self::assertSame(['singleton'], $schema->getTable('event_store_global_position')->getPrimaryKey()->getColumns());
    }

    public function test_that_configured_schema_preserves_unique_keys_with_distinct_index_names(): void
    {
        $defaultEvents = (new DbalEventStoreSchema())->schema()->getTable('event_store_events');
        $schema = (new DbalEventStoreSchema(new DbalEventStoreTables('Order', 'Group')))->schema();
        $configuredEvents = $schema->getTable('Order');
        self::assertTrue($configuredEvents->isQuoted());
        self::assertTrue($schema->getTable('Group')->isQuoted());
        self::assertSame(
            array_map(static fn ($index) => $index->getColumns(), array_values($defaultEvents->getIndexes())),
            array_map(static fn ($index) => $index->getColumns(), array_values($configuredEvents->getIndexes())),
        );
        self::assertCount(2, array_filter($configuredEvents->getIndexes(), static fn ($index) => $index->isUnique() && !$index->isPrimary()));
        self::assertSame([], array_intersect(
            array_keys($defaultEvents->getIndexes()),
            array_diff(array_keys($configuredEvents->getIndexes()), ['primary']),
        ));
    }

    public function test_that_install_creates_missing_tables_and_initializes_the_position(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('listTableNames')->once()->andReturn([]);
        $schemaManager->shouldReceive('createTable')->twice();
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);
        $connection->shouldReceive('fetchOne')->once()->andReturnFalse();
        $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new SQLitePlatform());
        $connection->shouldReceive('insert')->once()->with('"event_store_global_position"', [
            'singleton' => 1,
            'position' => 0,
        ]);

        (new DbalEventStoreSchema())->install($connection);
    }

    public function test_that_install_leaves_existing_tables_and_position_unchanged(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('listTableNames')->once()->andReturn(['event_store_events', 'event_store_global_position']);
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);
        $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new SQLitePlatform());
        $connection->shouldReceive('fetchOne')->once()->andReturn(1);
        $connection->shouldNotReceive('insert');

        (new DbalEventStoreSchema())->install($connection);
    }
}
