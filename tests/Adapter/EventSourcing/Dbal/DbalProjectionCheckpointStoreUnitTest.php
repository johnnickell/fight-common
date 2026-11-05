<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStore;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(DbalProjectionCheckpointStore::class)]
final class DbalProjectionCheckpointStoreUnitTest extends UnitTestCase
{
    public static function tableNames(): iterable
    {
        yield 'default' => ['projection_checkpoints'];
        yield 'configured reserved mixed case' => ['Order'];
    }

    public function test_that_construction_does_not_access_the_connection(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldNotReceive('getDatabasePlatform');
        $connection->shouldNotReceive('executeStatement');

        new DbalProjectionCheckpointStore(connection: $connection, table: new DbalTableName('Order'));
    }

    #[DataProvider('tableNames')]
    public function test_that_load_uses_the_selected_table_and_bound_identity(string $tableName): void
    {
        $connection = $this->checkpointConnection();
        $connection->shouldReceive('fetchOne')->twice()->with(
            'SELECT global_position FROM "'.$tableName.'" WHERE projector_name = ?',
            ["orders' OR 1=1"],
        )->andReturn(false, '42');
        $checkpointStore = new DbalProjectionCheckpointStore($connection, new DbalTableName($tableName));

        self::assertSame(0, $checkpointStore->load("orders' OR 1=1"));
        self::assertSame(42, $checkpointStore->load("orders' OR 1=1"));
    }

    public function test_that_save_rejects_a_negative_position_using_the_current_checkpoint(): void
    {
        $connection = $this->checkpointConnection();
        $connection->shouldReceive('fetchOne')->once()->andReturn(6);
        $checkpointStore = new DbalProjectionCheckpointStore(connection: $connection);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projection checkpoint orders cannot move backward from 6 to -1.');

        $checkpointStore->save('orders', -1);
    }

    #[DataProvider('tableNames')]
    public function test_that_save_advances_without_inserting(string $tableName): void
    {
        $connection = $this->checkpointConnection();
        $connection->shouldReceive('executeStatement')->once()->with(
            'UPDATE "'.$tableName.'" SET global_position = ? WHERE projector_name = ? AND global_position <= ?',
            [7, 'orders', 7],
        )->andReturn(1);
        $connection->shouldNotReceive('insert');

        new DbalProjectionCheckpointStore($connection, new DbalTableName($tableName))->save('orders', 7);
    }

    #[DataProvider('tableNames')]
    public function test_that_save_inserts_into_the_selected_table(string $tableName): void
    {
        $connection = $this->checkpointConnection();
        $connection->shouldReceive('executeStatement')->once()->andReturn(0);
        $connection->shouldReceive('insert')->once()->with('"'.$tableName.'"', [
            'projector_name' => 'orders',
            'global_position' => 7,
        ]);

        new DbalProjectionCheckpointStore($connection, new DbalTableName($tableName))->save('orders', 7);
    }

    #[DataProvider('tableNames')]
    public function test_that_save_recovers_an_insert_race_in_selected_storage(string $tableName): void
    {
        $connection = $this->checkpointConnection();
        $connection->shouldReceive('executeStatement')->twice()->with(
            'UPDATE "'.$tableName.'" SET global_position = ? WHERE projector_name = ? AND global_position <= ?',
            [7, 'orders', 7],
        )->andReturn(0, 1);
        $connection->shouldReceive('insert')->once()->with('"'.$tableName.'"', [
            'projector_name' => 'orders',
            'global_position' => 7,
        ])->andThrow($this->uniqueConstraintViolation());
        $connection->shouldReceive('fetchOne')->once()->with(
            'SELECT global_position FROM "'.$tableName.'" WHERE projector_name = ?',
            ['orders'],
        )->andReturn(7);

        new DbalProjectionCheckpointStore($connection, new DbalTableName($tableName))->save('orders', 7);
    }

    #[DataProvider('tableNames')]
    public function test_that_save_rejects_a_race_revealing_newer_progress(string $tableName): void
    {
        $connection = $this->checkpointConnection();
        $connection->shouldReceive('executeStatement')->twice()->andReturn(0);
        $connection->shouldReceive('insert')->once()->andThrow($this->uniqueConstraintViolation());
        $connection->shouldReceive('fetchOne')->once()->andReturn(8);
        $checkpointStore = new DbalProjectionCheckpointStore($connection, new DbalTableName($tableName));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projection checkpoint orders cannot move backward from 8 to 7.');

        $checkpointStore->save('orders', 7);
    }

    #[DataProvider('tableNames')]
    public function test_that_reset_updates_the_selected_table(string $tableName): void
    {
        $connection = $this->checkpointConnection();
        $connection->shouldReceive('update')->once()->with(
            '"'.$tableName.'"', ['global_position' => 0], ['projector_name' => 'orders'],
        )->andReturn(1);
        $connection->shouldNotReceive('insert');

        new DbalProjectionCheckpointStore($connection, new DbalTableName($tableName))->reset('orders');
    }

    #[DataProvider('tableNames')]
    public function test_that_reset_inserts_into_the_selected_table(string $tableName): void
    {
        $connection = $this->checkpointConnection();
        $connection->shouldReceive('update')->once()->andReturn(0);
        $connection->shouldReceive('insert')->once()->with('"'.$tableName.'"', [
            'projector_name' => 'orders',
            'global_position' => 0,
        ]);

        new DbalProjectionCheckpointStore($connection, new DbalTableName($tableName))->reset('orders');
    }

    #[DataProvider('tableNames')]
    public function test_that_reset_retries_a_concurrent_insert_in_selected_storage(string $tableName): void
    {
        $connection = $this->checkpointConnection();
        $connection->shouldReceive('update')->twice()->with(
            '"'.$tableName.'"', ['global_position' => 0], ['projector_name' => 'orders'],
        )->andReturn(0, 1);
        $connection->shouldReceive('insert')->once()->with('"'.$tableName.'"', [
            'projector_name' => 'orders',
            'global_position' => 0,
        ])->andThrow($this->uniqueConstraintViolation());

        new DbalProjectionCheckpointStore($connection, new DbalTableName($tableName))->reset('orders');
    }

    private function checkpointConnection(): Connection&MockInterface
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new SQLitePlatform());

        return $connection;
    }

    private function uniqueConstraintViolation(): UniqueConstraintViolationException
    {
        $driverFailure = new class('forced unique constraint race') extends RuntimeException implements DriverException {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        };

        return new UniqueConstraintViolationException($driverFailure, null);
    }
}
