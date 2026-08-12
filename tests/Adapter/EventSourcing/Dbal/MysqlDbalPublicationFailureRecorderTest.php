<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorder;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorderSchema;
use Fight\Test\Common\TestCase\EventSourcing\DbalPublicationFailureRecorderConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Class MysqlDbalPublicationFailureRecorderTest
 *
 * MySQL publication-failure recorder conformance tests
 */
#[CoversClass(DbalPublicationFailureRecorder::class)]
#[CoversClass(DbalPublicationFailureRecorderSchema::class)]
#[Group('server-database')]
final class MysqlDbalPublicationFailureRecorderTest extends DbalPublicationFailureRecorderConformanceTestCase
{
    /**
     * Requires the complete-suite MySQL connection
     */
    protected function setUp(): void
    {
        parent::setUp();

        self::assertIsString(
            getenv('FIGHT_COMMON_MYSQL_DSN'),
            'FIGHT_COMMON_MYSQL_DSN is required for the complete server-database suite.',
        );
    }

    /**
     * Creates a MySQL connection for the adapter under test
     */
    protected function createConnection(): Connection
    {
        $dsn = getenv('FIGHT_COMMON_MYSQL_DSN');
        self::assertIsString($dsn);
        $params = (new DsnParser(['mysql' => 'pdo_mysql']))->parse($dsn);

        return DriverManager::getConnection($params);
    }
}
