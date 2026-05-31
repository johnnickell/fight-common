<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Observability\Health;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Fight\Common\Adapter\Observability\Health\DatabaseHealthCheck;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(DatabaseHealthCheck::class)]
class DatabaseHealthCheckTest extends UnitTestCase
{
    public function test_that_check_returns_healthy_when_query_succeeds(): void
    {
        /** @var MockInterface|AbstractPlatform $platform */
        $platform = $this->mock(AbstractPlatform::class);
        $platform->shouldReceive('getDummySelectSQL')->andReturn('SELECT 1');

        /** @var MockInterface|Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn($platform);
        $connection->shouldReceive('executeQuery')->with('SELECT 1')->once();

        $check = new DatabaseHealthCheck($connection);
        $result = $check->check();

        self::assertSame('database', $result->name());
        self::assertTrue($result->status()->isHealthy());
        self::assertStringContainsString('ms', $result->message() ?? '');
    }

    public function test_that_check_returns_unhealthy_when_query_throws(): void
    {
        /** @var MockInterface|AbstractPlatform $platform */
        $platform = $this->mock(AbstractPlatform::class);
        $platform->shouldReceive('getDummySelectSQL')->andReturn('SELECT 1');

        /** @var MockInterface|Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn($platform);
        $connection->shouldReceive('executeQuery')->andThrow(new RuntimeException('Connection refused'));

        $check = new DatabaseHealthCheck($connection);
        $result = $check->check();

        self::assertTrue($result->status()->isUnhealthy());
        self::assertSame('Connection refused', $result->message());
    }

    public function test_that_custom_name_is_used(): void
    {
        /** @var MockInterface|AbstractPlatform $platform */
        $platform = $this->mock(AbstractPlatform::class);
        $platform->shouldReceive('getDummySelectSQL')->andReturn('SELECT 1');

        /** @var MockInterface|Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn($platform);
        $connection->shouldReceive('executeQuery')->once();

        $check = new DatabaseHealthCheck($connection, 'primary-db');

        self::assertSame('primary-db', $check->name());
        self::assertSame('primary-db', $check->check()->name());
    }
}
