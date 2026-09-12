<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStore;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalPublicationCursorStore::class)]
final class DbalPublicationCursorStoreUnitTest extends UnitTestCase
{
    public function test_that_load_defaults_to_the_start_of_history(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('fetchOne')->once()->andReturnFalse();

        self::assertSame(0, (new DbalPublicationCursorStore($connection))->load('orders'));
    }

    public function test_that_load_returns_the_stored_position(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('fetchOne')->once()->andReturn('42');

        self::assertSame(42, (new DbalPublicationCursorStore($connection))->load('orders'));
    }

    public function test_that_save_rejects_a_negative_position_using_the_current_cursor(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('fetchOne')->once()->andReturn(6);
        $store = new DbalPublicationCursorStore($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Publication cursor orders cannot move backward from 6 to -1.');

        $store->save('orders', -1);
    }

    public function test_that_save_advances_an_existing_cursor_without_an_insert(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('executeStatement')->once()->andReturn(1);
        $connection->shouldNotReceive('insert');

        (new DbalPublicationCursorStore($connection))->save('orders', 7);
    }

    public function test_that_save_inserts_a_new_cursor_when_no_existing_cursor_advances(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('executeStatement')->once()->andReturn(0);
        $connection->shouldReceive('insert')->once()->with('publication_cursors', [
            'publication_name' => 'orders',
            'global_position' => 7,
        ]);

        (new DbalPublicationCursorStore($connection))->save('orders', 7);
    }

    public function test_that_save_retries_an_insert_race_as_an_advance(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('executeStatement')->twice()->andReturn(0, 1);
        $connection->shouldReceive('insert')->once()->andThrow($this->uniqueConstraintViolation());
        $connection->shouldReceive('fetchOne')->once()->andReturn(7);

        (new DbalPublicationCursorStore($connection))->save('orders', 7);
    }

    public function test_that_save_rejects_an_insert_race_that_reveals_a_newer_cursor(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('executeStatement')->twice()->andReturn(0);
        $connection->shouldReceive('insert')->once()->andThrow($this->uniqueConstraintViolation());
        $connection->shouldReceive('fetchOne')->once()->andReturn(8);
        $store = new DbalPublicationCursorStore($connection);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Publication cursor orders cannot move backward from 8 to 7.');

        $store->save('orders', 7);
    }

    private function uniqueConstraintViolation(): UniqueConstraintViolationException
    {
        $driverFailure = new class('forced unique constraint race') extends \RuntimeException implements DriverException {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        };

        return new UniqueConstraintViolationException($driverFailure, null);
    }
}
