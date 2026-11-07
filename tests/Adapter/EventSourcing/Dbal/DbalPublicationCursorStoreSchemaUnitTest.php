<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStoreSchema;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalPublicationCursorStoreSchema::class)]
final class DbalPublicationCursorStoreSchemaUnitTest extends UnitTestCase
{
    public function test_that_schema_defines_the_default_cursor_table(): void
    {
        $schema = (new DbalPublicationCursorStoreSchema())->schema();
        self::assertTrue($schema->hasTable('publication_cursors'));
        self::assertSame(['publication_name'], $schema->getTable('publication_cursors')->getPrimaryKey()->getColumns());
    }

    public function test_that_install_creates_only_the_missing_selected_cursor_table(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('listTableNames')->once()->andReturn(['publication_cursors']);
        $schemaManager->shouldReceive('createTable')->once()->with(Mockery::on(
            static fn(Table $table): bool => 'Order' === $table->getName() && $table->isQuoted(),
        ));
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);
        (new DbalPublicationCursorStoreSchema(table: new DbalTableName('Order')))->install($connection);
    }

    public function test_that_install_recognizes_quoted_case_sensitive_existing_names(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('listTableNames')->once()->andReturn(['"Order"']);
        $schemaManager->shouldNotReceive('createTable');
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);
        (new DbalPublicationCursorStoreSchema(new DbalTableName('Order')))->install($connection);
    }
}
