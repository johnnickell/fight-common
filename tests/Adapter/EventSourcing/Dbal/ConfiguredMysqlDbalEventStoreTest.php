<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Test\Common\TestCase\EventSourcing\ConfiguredDbalEventStoreTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

#[CoversNothing]
#[Group('server-database')]
final class ConfiguredMysqlDbalEventStoreTest extends ConfiguredDbalEventStoreTestCase
{
    protected function createConnection(): Connection
    {
        $dsn = getenv('FIGHT_COMMON_MYSQL_DSN');
        self::assertIsString($dsn, 'The complete suite requires its native MySQL service.');

        return DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql']))->parse($dsn));
    }
}
