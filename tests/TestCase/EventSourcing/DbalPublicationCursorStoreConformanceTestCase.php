<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStoreSchema;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Common\Application\EventSourcing\PublicationCursorStore;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

/**
 * Class DbalPublicationCursorStoreConformanceTestCase
 *
 * Reusable Doctrine DBAL publication cursor contract
 */
abstract class DbalPublicationCursorStoreConformanceTestCase extends PublicationCursorStoreConformanceTestCase
{
    private string $tableName = 'publication_cursors';

    public static function selections(): iterable
    {
        yield 'default' => ['publication_cursors'];
        yield 'configured reserved word' => ['Order'];
    }

    public function test_that_configured_cursors_preserve_the_lifecycle_and_populated_defaults(): void
    {
        $this->tableName = 'Order';
        $this->test_that_named_publication_cursors_follow_the_durable_lifecycle_contract();
        $this->assertDefaultStorageUntouched();
    }

    public function test_that_selection_neither_installs_migrates_nor_falls_back(): void
    {
        $connection = $this->resetDatabase();
        new DbalPublicationCursorStore(connection: $connection)->save('orders.primary', 99);
        $table = new DbalTableName('Order');
        $sqlTable = $table->quoted($connection->getDatabasePlatform());
        $connection->executeStatement('DROP TABLE IF EXISTS '.$sqlTable);
        $cursorStore = new DbalPublicationCursorStore($connection, $table);
        $schema = new DbalPublicationCursorStoreSchema($table);
        $schema->schema();
        $this->assertStorageOperationsFail($cursorStore);
        self::assertSame(99, new DbalPublicationCursorStore($connection)->load('orders.primary'));
        $connection->executeStatement('CREATE TABLE '.$sqlTable.' (wrong_column INT)');
        $schema->install($connection);
        $this->assertStorageOperationsFail($cursorStore);
        self::assertSame(99, new DbalPublicationCursorStore($connection)->load('orders.primary'));
    }

    /**
     * Verifies competing workers cannot regress the durable cursor
     */
    #[DataProvider('selections')]
    public function test_that_concurrent_workers_cannot_regress_committed_progress(string $tableName): void
    {
        $this->tableName = $tableName;
        $setupConnection = $this->resetDatabase();
        new DbalPublicationCursorStore($setupConnection, new DbalTableName($tableName))->save('orders.secondary', 41);
        $setupConnection->close();
        $coordinationPath = sprintf(
            '%s/fight-common-publication-cursor-race-%s',
            sys_get_temp_dir(),
            bin2hex(random_bytes(8)),
        );
        mkdir($coordinationPath);
        $workerProcessIds = [];

        try {
            $lowerWorker = $this->forkCursorWorker($coordinationPath, 'lower', 7);
            $workerProcessIds[$lowerWorker] = true;
            $higherWorker = $this->forkCursorWorker($coordinationPath, 'higher', 13);
            $workerProcessIds[$higherWorker] = true;

            for ($round = 1; $round <= 24; ++$round) {
                $this->waitForFile(sprintf('%s/lower-ready-%d', $coordinationPath, $round));
                $this->waitForFile(sprintf('%s/higher-ready-%d', $coordinationPath, $round));
                file_put_contents(sprintf('%s/go-%d', $coordinationPath, $round), 'go');
                $this->waitForFile(sprintf('%s/lower-done-%d', $coordinationPath, $round));
                $this->waitForFile(sprintf('%s/higher-done-%d', $coordinationPath, $round));
            }

            if ($lowerWorker === pcntl_waitpid($lowerWorker, $lowerStatus)) {
                unset($workerProcessIds[$lowerWorker]);
            }

            if ($higherWorker === pcntl_waitpid($higherWorker, $higherStatus)) {
                unset($workerProcessIds[$higherWorker]);
            }

            $lowerResult = file_get_contents(sprintf('%s/lower-result', $coordinationPath));
            $higherResult = file_get_contents(sprintf('%s/higher-result', $coordinationPath));

            self::assertSame(0, pcntl_wexitstatus($lowerStatus));
            self::assertSame(0, pcntl_wexitstatus($higherStatus));
            self::assertSame('', $lowerResult);
            self::assertSame('', $higherResult);
            $cursorStore = new DbalPublicationCursorStore($this->createConnection(), new DbalTableName($tableName));
            self::assertSame(253, $cursorStore->load('orders.primary'));
            self::assertSame(41, $cursorStore->load('orders.secondary'));
            if ('publication_cursors' !== $tableName) {
                $this->assertDefaultStorageUntouched();
            }
        } finally {
            foreach (array_keys($workerProcessIds) as $workerProcessId) {
                $waitResult = pcntl_waitpid($workerProcessId, $workerStatus, WNOHANG);

                if (0 === $waitResult) {
                    posix_kill($workerProcessId, SIGTERM);
                    pcntl_waitpid($workerProcessId, $workerStatus);
                }
            }

            foreach (glob(sprintf('%s/*', $coordinationPath)) ?: [] as $path) {
                unlink($path);
            }

            rmdir($coordinationPath);
        }
    }

    /**
     * Creates a database connection for the adapter under test
     */
    abstract protected function createConnection(): Connection;

    /**
     * Creates an installed publication cursor store
     */
    protected function createPublicationCursorStore(): PublicationCursorStore
    {
        return new DbalPublicationCursorStore($this->resetDatabase(), new DbalTableName($this->tableName));
    }

    /**
     * Returns the publication cursor store with a new connection
     */
    protected function reopenPublicationCursorStore(
        PublicationCursorStore $store,
    ): PublicationCursorStore {
        unset($store);

        return new DbalPublicationCursorStore($this->createConnection(), new DbalTableName($this->tableName));
    }

    /**
     * Creates an empty installed publication cursor schema
     */
    protected function resetDatabase(): Connection
    {
        $connection = $this->createConnection();
        foreach (['publication_cursors', 'Order'] as $name) {
            $table = new DbalTableName($name);
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->quoted($connection->getDatabasePlatform()));
        }

        $schema = new DbalPublicationCursorStoreSchema(new DbalTableName($this->tableName));
        $schema->install($connection);
        $schema->install($connection);

        if ('publication_cursors' !== $this->tableName) {
            (new DbalPublicationCursorStoreSchema())->install($connection);
            $defaultCursorStore = new DbalPublicationCursorStore($connection);
            $defaultCursorStore->save('orders.primary', 999);
            $defaultCursorStore->save('orders.secondary', 999);
        }
        self::assertSame(0, new DbalPublicationCursorStore(
            $connection, new DbalTableName($this->tableName),
        )->load('orders.primary'));

        return $connection;
    }

    /**
     * Creates one worker participating in the cursor race
     */
    private function forkCursorWorker(string $coordinationPath, string $worker, int $offset): int
    {
        $processId = pcntl_fork();

        if (-1 === $processId) {
            throw new RuntimeException(sprintf('Unable to fork the %s cursor worker.', $worker));
        }

        if (0 !== $processId) {
            return $processId;
        }

        $cursorStore = new DbalPublicationCursorStore($this->createConnection(), new DbalTableName($this->tableName));
        $failures = [];

        for ($round = 1; $round <= 24; ++$round) {
            file_put_contents(sprintf('%s/%s-ready-%d', $coordinationPath, $worker, $round), 'ready');
            $this->waitForFile(sprintf('%s/go-%d', $coordinationPath, $round));

            try {
                $cursorStore->save('orders.primary', ($round * 10) + $offset);
            } catch (InvalidArgumentException) {
                // A lower worker may observe that the higher position already won.
            } catch (Throwable $exception) {
                $failures[] = $exception::class.': '.$exception->getMessage();
            }

            file_put_contents(sprintf('%s/%s-done-%d', $coordinationPath, $worker, $round), 'done');
        }

        file_put_contents(sprintf('%s/%s-result', $coordinationPath, $worker), implode("\n", $failures));
        exit(0);
    }

    private function assertDefaultStorageUntouched(): void
    {
        $connection = $this->createConnection();
        self::assertSame([
            ['publication_name' => 'orders.primary', 'global_position' => 999],
            ['publication_name' => 'orders.secondary', 'global_position' => 999],
        ], $connection->fetchAllAssociative('SELECT * FROM publication_cursors ORDER BY publication_name'));
    }

    private function assertStorageOperationsFail(DbalPublicationCursorStore $cursorStore): void
    {
        foreach (['load', 'save'] as $operation) {
            try {
                if ('save' === $operation) {
                    $cursorStore->save('orders.primary', 1);
                } else {
                    $cursorStore->load('orders.primary');
                }
                self::fail('Expected missing/incompatible selected cursor storage to fail.');
            } catch (Exception) {
                self::assertTrue(true);
            }
        }
    }

    /**
     * Returns after observing a worker coordination signal
     */
    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 10;

        while (!file_exists($path)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException(sprintf('Timed out waiting for cursor race signal %s.', $path));
            }

            usleep(1_000);
        }
    }
}
