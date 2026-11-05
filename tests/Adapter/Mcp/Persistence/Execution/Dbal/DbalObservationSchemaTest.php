<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationTables;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(DbalObservationSchema::class)]
final class DbalObservationSchemaTest extends UnitTestCase
{
    private const array CONFIGURED_NAMES = ['Order', 'Group', 'Phase', 'Observation', 'Content'];

    #[DataProvider('platforms')]
    public function test_that_configured_assets_and_relationships_are_quoted_without_changing_defaults(
        AbstractPlatform $platform
    ): void {
        $schema = new Schema();
        $consumerTable = $schema->createTable('consumer_owned');
        $consumerTable->addColumn('id', 'integer');
        $consumerOptions = $consumerTable->getOptions();
        (new DbalObservationSchema())->addTo($schema, $platform);
        $configuredSchema = new DbalObservationSchema(new DbalObservationTables(...self::CONFIGURED_NAMES));
        $configuredSchema->addTo($schema, $platform);

        self::assertSame($consumerTable, $schema->getTable('consumer_owned'));
        self::assertSame($consumerOptions, $consumerTable->getOptions());
        foreach (self::CONFIGURED_NAMES as $position => $name) {
            $table = $schema->getTable($name);
            $defaultTable = $schema->getTable(DbalObservationSchema::TABLES[$position]);
            self::assertTrue($table->isQuoted());
            self::assertSame($platform->quoteSingleIdentifier($name), $table->getQuotedName($platform));
            self::assertSame($defaultTable->getPrimaryKey()->getColumns(), $table->getPrimaryKey()->getColumns());
            self::assertSame(array_keys($defaultTable->getColumns()), array_keys($table->getColumns()));
            self::assertSame($defaultTable->getOptions(), $table->getOptions());
            if ($platform instanceof MySQLPlatform) {
                self::assertSame('InnoDB', $table->getOption('engine'));
                self::assertSame('utf8mb4', $table->getOption('charset'));
                self::assertSame('utf8mb4_bin', $table->getOption('collation'));
            }
        }
        foreach ([
            'Phase' => ['Group', 'Phase'],
            'Observation' => ['Group', 'Phase', 'Observation'],
            'Content' => ['Observation'],
        ] as $name => $targets) {
            $foreignKeys = array_values($schema->getTable($name)->getForeignKeys());
            self::assertSame($targets, array_map(static fn ($key) => $key->getForeignTableName(), $foreignKeys));
            self::assertSame(
                array_map($platform->quoteSingleIdentifier(...), $targets),
                array_map(static fn ($key) => $key->getQuotedForeignTableName($platform), $foreignKeys),
            );
        }
        $contentForeignKey = array_values($schema->getTable('Content')->getForeignKeys())[0];
        self::assertSame('CASCADE', $contentForeignKey->getOption('onDelete'));
        self::assertFalse($schema->getTable('Observation')->getColumn('activity_id')->getNotnull());
        self::assertSame(16777215, $schema->getTable('Content')->getColumn('payload')->getLength());
        self::assertTrue($schema->getTable('fight_execution_phases')->hasIndex('fight_execution_phase_session'));
        self::assertTrue($schema->getTable('fight_execution_observations')->hasIndex('fight_execution_recording_order'));

        $secondSchema = new Schema();
        $configuredSchema->addTo($secondSchema, $platform);
        foreach (['Phase', 'Observation'] as $name) {
            $table = $schema->getTable($name);
            $defaultName = $name === 'Phase' ? 'fight_execution_phases' : 'fight_execution_observations';
            $indexNames = array_diff(array_keys($table->getIndexes()), ['primary']);
            self::assertSame([], array_intersect($indexNames, array_keys($schema->getTable($defaultName)->getIndexes())));
            self::assertSame(array_keys($table->getIndexes()), array_keys($secondSchema->getTable($name)->getIndexes()));
            foreach ($indexNames as $indexName) {
                self::assertLessThanOrEqual(63, strlen($indexName));
            }
        }
        $phaseUniqueIndexes = array_values(array_filter(
            $schema->getTable('Phase')->getIndexes(),
            static fn ($index) => $index->isUnique() && !$index->isPrimary(),
        ));
        self::assertCount(1, $phaseUniqueIndexes);
        self::assertSame(['project_id', 'session_id', 'phase_id'], $phaseUniqueIndexes[0]->getColumns());
    }

    #[DataProvider('selections')]
    public function test_that_install_creates_only_selected_tables_and_initializes_quoted_state(
        array $overrides,
        array $names
    ): void {
        $schema = new Schema();
        $consumerTable = $schema->createTable('consumer_owned');
        $consumerTable->addColumn('id', 'integer');
        $existingNames = ['consumer_owned'];
        if (count($overrides) === 5) {
            (new DbalObservationSchema())->addTo($schema, new PostgreSQLPlatform());
            $existingNames = [...$existingNames, ...DbalObservationSchema::TABLES];
        }
        $manager = $this->mock(AbstractSchemaManager::class);
        $manager->shouldReceive('listTableNames')->once()->andReturn($existingNames);
        $manager->shouldReceive('introspectSchema')->once()->andReturn($schema);
        foreach ($names as $name) {
            $manager->shouldReceive('createTable')->once()->withArgs(
                static fn (Table $table): bool => $table->getName() === $name && $table->isQuoted(),
            )->ordered();
        }
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($manager);
        $connection->shouldReceive('getDatabasePlatform')->twice()->andReturn(new PostgreSQLPlatform());
        $connection->shouldReceive('insert')->once()->with('"'.$names[0].'"', ['singleton' => 1, 'version' => 1]);
        $connection->shouldNotReceive('fetchOne');

        (new DbalObservationSchema(new DbalObservationTables(...$overrides)))->install($connection);

        self::assertSame($consumerTable, $schema->getTable('consumer_owned'));
    }

    #[DataProvider('selections')]
    public function test_that_install_validates_selected_existing_tables_with_dbal_quote_markers(
        array $overrides,
        array $names
    ): void {
        $installer = new DbalObservationSchema(new DbalObservationTables(...$overrides));
        $schema = new Schema();
        $installer->addTo($schema, new PostgreSQLPlatform());
        $manager = $this->mock(AbstractSchemaManager::class);
        $existingNames = array_map(static fn (string $name): string => '`'.$name.'`', $names);
        $existingNames[0] = '"'.$names[0].'"';
        $manager->shouldReceive('listTableNames')->once()->andReturn([...$existingNames, 'unrelated']);
        $manager->shouldReceive('introspectTable')->once()->with('`'.$names[3].'`')->andReturn($schema->getTable($names[3]));
        $manager->shouldNotReceive('createTable');
        $manager->shouldNotReceive('introspectSchema');
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($manager);
        $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new PostgreSQLPlatform());
        $connection->shouldReceive('fetchOne')->once()->with('SELECT version FROM "'.$names[0].'" WHERE singleton = 1')->andReturn('1');
        $connection->shouldNotReceive('insert');

        $installer->install($connection);
    }

    public function test_that_install_rejects_caller_transactions_before_inspection(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnTrue();
        $connection->shouldNotReceive('createSchemaManager');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Observation schema installation requires an idle connection.');

        (new DbalObservationSchema())->install($connection);
    }

    public function test_that_partial_selected_schema_rejects_without_version_query_or_default_fallback(): void
    {
        $manager = $this->mock(AbstractSchemaManager::class);
        $manager->shouldReceive('listTableNames')->once()->andReturn([...DbalObservationSchema::TABLES, 'Order']);
        $manager->shouldNotReceive('createTable');
        $manager->shouldNotReceive('introspectTable');
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($manager);
        $connection->shouldNotReceive('fetchOne');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported or incomplete observation schema.');

        (new DbalObservationSchema(new DbalObservationTables(...self::CONFIGURED_NAMES)))->install($connection);
    }

    #[DataProvider('invalidInstallations')]
    public function test_that_selected_schema_rejects_unsupported_or_incomplete_version_one(
        mixed $version,
        string $defect
    ): void {
        $installer = new DbalObservationSchema(new DbalObservationTables(...self::CONFIGURED_NAMES));
        $schema = new Schema();
        $installer->addTo($schema, new PostgreSQLPlatform());
        $table = $schema->getTable('Observation');
        if ($defect === 'index') {
            foreach ($table->getIndexes() as $index) {
                if ($index->getColumns() === ['recorded_at', 'observation_id']) {
                    $table->dropIndex($index->getName());
                }
            }
            // The historical default index is not the selected table's integrity marker.
            $table->addIndex(['recorded_at', 'observation_id'], 'fight_execution_recording_order');
        } elseif ($defect === 'activity') {
            $table->dropColumn('activity_id');
        }
        $manager = $this->mock(AbstractSchemaManager::class);
        $manager->shouldReceive('listTableNames')->once()->andReturn(self::CONFIGURED_NAMES);
        $manager->shouldNotReceive('createTable');
        if ($defect !== 'version') {
            $manager->shouldReceive('introspectTable')->once()->with('`Observation`')->andReturn($table);
        } else {
            $manager->shouldNotReceive('introspectTable');
        }
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();
        $connection->shouldReceive('createSchemaManager')->once()->andReturn($manager);
        $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new PostgreSQLPlatform());
        $connection->shouldReceive('fetchOne')->once()->with('SELECT version FROM "Order" WHERE singleton = 1')->andReturn($version);
        $connection->shouldNotReceive('insert');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($defect === 'version'
            ? 'Unsupported or incomplete observation schema.'
            : 'Incomplete observation schema version one.');

        $installer->install($connection);
    }

    public static function platforms(): iterable
    {
        yield 'sqlite' => [new SQLitePlatform()];
        yield 'mysql' => [new MySQLPlatform()];
        yield 'postgresql' => [new PostgreSQLPlatform()];
    }

    public static function selections(): iterable
    {
        yield 'defaults' => [[], DbalObservationSchema::TABLES];
        yield 'configured' => [self::CONFIGURED_NAMES, self::CONFIGURED_NAMES];
        yield 'partial' => [
            ['schemaTable' => 'Order', 'observationTable' => 'Observation'],
            ['Order', 'fight_execution_sessions', 'fight_execution_phases', 'Observation', 'fight_execution_contents'],
        ];
    }

    public static function invalidInstallations(): iterable
    {
        yield 'missing state' => [false, 'version'];
        yield 'unsupported version' => [2, 'version'];
        yield 'missing selected index' => [1, 'index'];
        yield 'missing activity column' => [1, 'activity'];
    }
}
