<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorder;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorderSchema;
use Fight\Test\Common\TestCase\EventSourcing\DbalPublicationFailureRecorderConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class DbalPublicationFailureRecorderTest
 *
 * SQLite publication-failure recorder conformance tests
 */
#[CoversClass(DbalPublicationFailureRecorder::class)]
#[CoversClass(DbalPublicationFailureRecorderSchema::class)]
final class DbalPublicationFailureRecorderTest extends DbalPublicationFailureRecorderConformanceTestCase
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
                '%s/fight-common-publication-failures-%s.sqlite',
                sys_get_temp_dir(),
                bin2hex(random_bytes(8)),
            );
        }

        return DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path'   => $this->databasePath
        ]);
    }
}
