<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorderSchema;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalPublicationFailureRecorderSchema::class)]
final class DbalPublicationFailureRecorderSchemaUnitTest extends UnitTestCase
{
    public function test_that_schema_defines_the_failure_and_handler_failure_tables(): void
    {
        $schema = (new DbalPublicationFailureRecorderSchema())->schema();

        self::assertTrue($schema->hasTable('publication_failures'));
        self::assertTrue($schema->hasTable('publication_handler_failures'));
        self::assertSame(
            ['publication_name', 'global_position', 'handler_position'],
            $schema->getTable('publication_handler_failures')->getPrimaryKey()->getColumns(),
        );
    }

    public function test_that_install_creates_each_missing_table(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('tablesExist')->twice()->andReturnFalse();
        $schemaManager->shouldReceive('createTable')->twice();
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);

        (new DbalPublicationFailureRecorderSchema())->install($connection);
    }

    public function test_that_install_leaves_existing_failure_tables_unchanged(): void
    {
        $schemaManager = $this->mock(AbstractSchemaManager::class);
        $schemaManager->shouldReceive('tablesExist')->twice()->andReturnTrue();
        $schemaManager->shouldNotReceive('createTable');
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($schemaManager);

        (new DbalPublicationFailureRecorderSchema())->install($connection);
    }
}
