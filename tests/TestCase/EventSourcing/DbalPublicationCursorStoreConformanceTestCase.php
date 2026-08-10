<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStoreSchema;
use Fight\Common\Application\EventSourcing\PublicationCursorStore;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Class DbalPublicationCursorStoreConformanceTestCase
 *
 * Reusable Doctrine DBAL publication cursor contract
 */
abstract class DbalPublicationCursorStoreConformanceTestCase extends PublicationCursorStoreConformanceTestCase
{
    /**
     * Verifies competing workers cannot regress the durable cursor
     */
    public function test_that_concurrent_workers_cannot_regress_committed_progress(): void
    {
        $setupConnection = $this->resetDatabase();
        new DbalPublicationCursorStore($setupConnection)->save('orders.secondary', 41);
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
            $store = new DbalPublicationCursorStore($this->createConnection());
            self::assertSame(253, $store->load('orders.primary'));
            self::assertSame(41, $store->load('orders.secondary'));
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
        return new DbalPublicationCursorStore($this->resetDatabase());
    }

    /**
     * Returns the publication cursor store with a new connection
     */
    protected function reopenPublicationCursorStore(
        PublicationCursorStore $store,
    ): PublicationCursorStore {
        unset($store);

        return new DbalPublicationCursorStore($this->createConnection());
    }

    /**
     * Creates an empty installed publication cursor schema
     */
    protected function resetDatabase(): Connection
    {
        $connection = $this->createConnection();
        $connection->executeStatement('DROP TABLE IF EXISTS publication_cursors');

        $schema = new DbalPublicationCursorStoreSchema();
        $schema->install($connection);
        $schema->install($connection);

        self::assertTrue($connection->createSchemaManager()->tablesExist([
            'publication_cursors'
        ]));

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

        $cursorStore = new DbalPublicationCursorStore($this->createConnection());
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
