<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStoreSchema;
use Fight\Test\Common\TestCase\EventSourcing\DbalPublicationCursorStoreConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Class PostgresqlDbalPublicationCursorStoreTest
 *
 * PostgreSQL publication cursor conformance tests
 */
#[CoversClass(DbalPublicationCursorStore::class)]
#[CoversClass(DbalPublicationCursorStoreSchema::class)]
#[Group('server-database')]
final class PostgresqlDbalPublicationCursorStoreTest extends DbalPublicationCursorStoreConformanceTestCase
{
    /**
     * Requires the complete-suite PostgreSQL connection
     */
    protected function setUp(): void
    {
        parent::setUp();

        self::assertIsString(
            getenv('FIGHT_COMMON_POSTGRESQL_DSN'),
            'FIGHT_COMMON_POSTGRESQL_DSN is required for the complete server-database suite.',
        );
    }

    /**
     * Creates a PostgreSQL connection for the adapter under test
     */
    protected function createConnection(): Connection
    {
        $dsn = getenv('FIGHT_COMMON_POSTGRESQL_DSN');
        self::assertIsString($dsn);
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($dsn);

        return DriverManager::getConnection($params);
    }
}
