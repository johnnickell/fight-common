<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStoreSchema;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalProjectionCheckpointStoreSchema::class)]
final class DbalProjectionCheckpointStoreSchemaUnitTest extends UnitTestCase
{
    public function test_that_schema_defines_the_projection_checkpoint_table(): void
    {
        $schema = (new DbalProjectionCheckpointStoreSchema())->schema();

        self::assertTrue($schema->hasTable('projection_checkpoints'));
        self::assertSame(['projector_name'], $schema->getTable('projection_checkpoints')->getPrimaryKey()->getColumns());
    }

    public function test_that_install_creates_only_a_missing_checkpoint_table(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('tablesExist')->once()->andReturnFalse();
        $schemaManager->shouldReceive('createTable')->once();
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);

        (new DbalProjectionCheckpointStoreSchema())->install($connection);
    }

    public function test_that_install_is_a_no_op_when_the_checkpoint_table_exists(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('tablesExist')->once()->andReturnTrue();
        $schemaManager->shouldNotReceive('createTable');
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);

        (new DbalProjectionCheckpointStoreSchema())->install($connection);
    }
}
