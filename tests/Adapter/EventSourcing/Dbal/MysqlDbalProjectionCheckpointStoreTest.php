<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStoreSchema;
use Fight\Test\Common\TestCase\EventSourcing\DbalProjectionCheckpointStoreConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DbalProjectionCheckpointStore::class)]
#[CoversClass(DbalProjectionCheckpointStoreSchema::class)]
#[Group('server-database')]
final class MysqlDbalProjectionCheckpointStoreTest extends DbalProjectionCheckpointStoreConformanceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::assertIsString(
            getenv('FIGHT_COMMON_MYSQL_DSN'),
            'FIGHT_COMMON_MYSQL_DSN is required for the complete server-database suite.',
        );
    }

    protected function createConnection(): Connection
    {
        $dsn = getenv('FIGHT_COMMON_MYSQL_DSN');
        self::assertIsString($dsn);
        $params = (new DsnParser(['mysql' => 'pdo_mysql']))->parse($dsn);

        return DriverManager::getConnection($params);
    }
}
