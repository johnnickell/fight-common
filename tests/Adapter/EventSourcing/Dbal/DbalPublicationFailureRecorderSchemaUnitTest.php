<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorderSchema;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureTables;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalPublicationFailureRecorderSchema::class)]
final class DbalPublicationFailureRecorderSchemaUnitTest extends UnitTestCase
{
    public function test_that_schema_defines_the_default_failure_and_handler_tables(): void
    {
        $schema = (new DbalPublicationFailureRecorderSchema())->schema();
        self::assertTrue($schema->hasTable('publication_failures'));
        self::assertTrue($schema->hasTable('publication_handler_failures'));
        self::assertSame(
            ['publication_name', 'global_position', 'handler_position'],
            $schema->getTable('publication_handler_failures')->getPrimaryKey()->getColumns(),
        );
    }

    public function test_that_configured_schema_quotes_both_tables_and_the_parent_reference(): void
    {
        $schema = (new DbalPublicationFailureRecorderSchema(new DbalPublicationFailureTables('Order', 'Group')))->schema();
        self::assertTrue($schema->getTable('Order')->isQuoted());
        $handlerTable = $schema->getTable('Group');
        self::assertTrue($handlerTable->isQuoted());
        $foreignKey = array_values($handlerTable->getForeignKeys())[0];
        self::assertSame('Order', $foreignKey->getForeignTableName());
        self::assertSame('CASCADE', $foreignKey->getOption('onDelete'));
    }

    public function test_that_install_creates_each_missing_selected_table_in_dependency_order(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('listTableNames')->once()->andReturn([]);
        foreach (['Order', 'Group'] as $name) {
            $schemaManager->shouldReceive('createTable')->once()->ordered()->with(Mockery::on(
                static fn(Table $table): bool => $name === $table->getName() && $table->isQuoted(),
            ));
        }
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);
        (new DbalPublicationFailureRecorderSchema(new DbalPublicationFailureTables('Order', 'Group')))->install($connection);
    }

    public function test_that_install_leaves_quoted_existing_selected_tables_unchanged(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('listTableNames')->once()->andReturn(['"Order"', '`Group`']);
        $schemaManager->shouldNotReceive('createTable');
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);
        (new DbalPublicationFailureRecorderSchema(new DbalPublicationFailureTables('Order', 'Group')))->install($connection);
    }
}
