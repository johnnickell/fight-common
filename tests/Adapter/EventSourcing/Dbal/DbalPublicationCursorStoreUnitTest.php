<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStore;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(DbalPublicationCursorStore::class)]
final class DbalPublicationCursorStoreUnitTest extends UnitTestCase
{
    private Connection&MockInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->mock(Connection::class);
        $this->connection->shouldReceive('getDatabasePlatform')->andReturn(new SQLitePlatform());
    }

    public static function selections(): iterable
    {
        yield 'default' => [null, '"publication_cursors"'];
        yield 'configured reserved word' => [new DbalTableName('Order'), '"Order"'];
    }

    #[DataProvider('selections')]
    public function test_that_load_binds_the_name_and_returns_stored_progress(?DbalTableName $table, string $sqlTable): void
    {
        $this->connection->shouldReceive('fetchOne')->twice()->with(
            'SELECT global_position FROM '.$sqlTable.' WHERE publication_name = ?', ["orders'pipeline"],
        )->andReturn(false, '42');
        $publicationCursorStore = $this->cursorStore($table);
        self::assertSame(0, $publicationCursorStore->load("orders'pipeline"));
        self::assertSame(42, $publicationCursorStore->load("orders'pipeline"));
    }

    public function test_that_save_rejects_a_negative_position_using_the_current_cursor(): void
    {
        $this->connection->shouldReceive('fetchOne')->once()->andReturn(6);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Publication cursor orders cannot move backward from 6 to -1.');
        $this->cursorStore(null)->save('orders', -1);
    }

    #[DataProvider('selections')]
    public function test_that_save_advances_without_inserting(?DbalTableName $table, string $sqlTable): void
    {
        $this->connection->shouldReceive('executeStatement')->once()->with(
            'UPDATE '.$sqlTable.' SET global_position = ? WHERE publication_name = ? AND global_position <= ?',
            [7, 'orders', 7],
        )->andReturn(1);
        $this->connection->shouldNotReceive('insert');
        $this->cursorStore($table)->save('orders', 7);
    }

    #[DataProvider('selections')]
    public function test_that_save_inserts_new_progress(?DbalTableName $table, string $sqlTable): void
    {
        $this->connection->shouldReceive('executeStatement')->once()->andReturn(0);
        $this->connection->shouldReceive('insert')->once()->with($sqlTable, [
            'publication_name' => 'orders', 'global_position' => 7,
        ]);
        $this->cursorStore($table)->save('orders', 7);
    }

    #[DataProvider('selections')]
    public function test_that_save_retries_insert_races_on_the_selected_table(?DbalTableName $table, string $sqlTable): void
    {
        $this->connection->shouldReceive('executeStatement')->twice()->with(
            'UPDATE '.$sqlTable.' SET global_position = ? WHERE publication_name = ? AND global_position <= ?',
            [7, 'orders', 7],
        )->andReturn(0, 1);
        $this->connection->shouldReceive('insert')->once()->with($sqlTable, [
            'publication_name' => 'orders', 'global_position' => 7,
        ])->andThrow($this->uniqueConstraintViolation());
        $this->connection->shouldReceive('fetchOne')->once()->with(
            'SELECT global_position FROM '.$sqlTable.' WHERE publication_name = ?', ['orders'],
        )->andReturn(7);
        $this->cursorStore($table)->save('orders', 7);
    }

    public function test_that_save_rejects_an_insert_race_that_reveals_newer_progress(): void
    {
        $this->connection->shouldReceive('executeStatement')->twice()->andReturn(0);
        $this->connection->shouldReceive('insert')->once()->andThrow($this->uniqueConstraintViolation());
        $this->connection->shouldReceive('fetchOne')->once()->andReturn(8);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Publication cursor orders cannot move backward from 8 to 7.');
        $this->cursorStore(new DbalTableName('Order'))->save('orders', 7);
    }

    private function cursorStore(?DbalTableName $table): DbalPublicationCursorStore
    {
        if (null === $table) {
            return new DbalPublicationCursorStore(connection: $this->connection);
        }

        return new DbalPublicationCursorStore(connection: $this->connection, table: $table);
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
