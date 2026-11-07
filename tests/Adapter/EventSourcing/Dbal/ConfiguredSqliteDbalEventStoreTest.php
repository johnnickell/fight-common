<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Test\Common\TestCase\EventSourcing\ConfiguredDbalEventStoreTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ConfiguredSqliteDbalEventStoreTest extends ConfiguredDbalEventStoreTestCase
{
    private string $databasePath;
    private array $connections = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = sys_get_temp_dir().'/fight-configured-events-'.bin2hex(random_bytes(8)).'.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
        foreach ([$this->databasePath, $this->databasePath.'-wal', $this->databasePath.'-shm'] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    protected function createConnection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->databasePath]);
        $connection->executeStatement('PRAGMA busy_timeout = 5000');
        $this->connections[] = $connection;

        return $connection;
    }
}
