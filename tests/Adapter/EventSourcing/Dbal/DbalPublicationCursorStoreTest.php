<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStoreSchema;
use Fight\Test\Common\TestCase\EventSourcing\DbalPublicationCursorStoreConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class DbalPublicationCursorStoreTest
 *
 * SQLite publication cursor conformance tests
 */
#[CoversClass(DbalPublicationCursorStore::class)]
#[CoversClass(DbalPublicationCursorStoreSchema::class)]
final class DbalPublicationCursorStoreTest extends DbalPublicationCursorStoreConformanceTestCase
{
    private ?string $databasePath = null;

    /**
     * Removes the temporary SQLite database
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        if (null !== $this->databasePath && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    /**
     * Creates a SQLite connection for the adapter under test
     */
    protected function createConnection(): Connection
    {
        if (null === $this->databasePath) {
            $this->databasePath = sprintf(
                '%s/fight-common-publication-cursors-%s.sqlite',
                sys_get_temp_dir(),
                bin2hex(random_bytes(8)),
            );
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path'   => $this->databasePath
        ]);
        $connection->executeStatement('PRAGMA busy_timeout = 5000');

        return $connection;
    }

    /**
     * Verifies the schema installer remains independent from the event store
     */
    protected function resetDatabase(): Connection
    {
        $connection = parent::resetDatabase();

        self::assertFalse($connection->createSchemaManager()->tablesExist([
            'event_store_events'
        ]));

        return $connection;
    }
}
