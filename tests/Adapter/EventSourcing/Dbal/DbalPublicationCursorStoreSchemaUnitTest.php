<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStoreSchema;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalPublicationCursorStoreSchema::class)]
final class DbalPublicationCursorStoreSchemaUnitTest extends UnitTestCase
{
    public function test_that_schema_defines_the_publication_cursor_table(): void
    {
        $schema = (new DbalPublicationCursorStoreSchema())->schema();

        self::assertTrue($schema->hasTable('publication_cursors'));
        self::assertSame(['publication_name'], $schema->getTable('publication_cursors')->getPrimaryKey()->getColumns());
    }

    public function test_that_install_creates_only_a_missing_cursor_table(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('tablesExist')->once()->andReturnFalse();
        $schemaManager->shouldReceive('createTable')->once();
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);

        (new DbalPublicationCursorStoreSchema())->install($connection);
    }

    public function test_that_install_is_a_no_op_when_the_cursor_table_exists(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('tablesExist')->once()->andReturnTrue();
        $schemaManager->shouldNotReceive('createTable');
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);

        (new DbalPublicationCursorStoreSchema())->install($connection);
    }
}
