<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStoreSchema;
use Fight\Test\Common\TestCase\EventSourcing\DbalProjectionCheckpointStoreConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalProjectionCheckpointStore::class)]
#[CoversClass(DbalProjectionCheckpointStoreSchema::class)]
final class DbalProjectionCheckpointStoreTest extends DbalProjectionCheckpointStoreConformanceTestCase
{
    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if (null !== $this->databasePath && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    protected function createConnection(): Connection
    {
        if (null === $this->databasePath) {
            $this->databasePath = sprintf(
                '%s/fight-common-projection-checkpoints-%s.sqlite',
                sys_get_temp_dir(),
                bin2hex(random_bytes(8)),
            );
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $this->databasePath,
        ]);
        $connection->executeStatement('PRAGMA busy_timeout = 5000');

        return $connection;
    }

    protected function resetDatabase(): Connection
    {
        $connection = parent::resetDatabase();

        self::assertFalse($connection->createSchemaManager()->tablesExist([
            'event_store_events',
        ]));

        return $connection;
    }
}
