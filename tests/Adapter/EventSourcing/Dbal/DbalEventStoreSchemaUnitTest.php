<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreSchema;
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

    public function test_that_install_creates_missing_tables_and_initializes_the_position(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('tablesExist')->twice()->andReturnFalse();
        $schemaManager->shouldReceive('createTable')->twice();
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);
        $connection->shouldReceive('fetchOne')->once()->andReturnFalse();
        $connection->shouldReceive('insert')->once()->with('event_store_global_position', [
            'singleton' => 1,
            'position' => 0,
        ]);

        (new DbalEventStoreSchema())->install($connection);
    }

    public function test_that_install_leaves_existing_tables_and_position_unchanged(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('tablesExist')->twice()->andReturnTrue();
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);
        $connection->shouldReceive('fetchOne')->once()->andReturn(1);
        $connection->shouldNotReceive('insert');

        (new DbalEventStoreSchema())->install($connection);
    }
}
