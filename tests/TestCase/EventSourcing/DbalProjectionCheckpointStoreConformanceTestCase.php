<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStoreSchema;
use Fight\Common\Application\EventSourcing\ProjectionCheckpointStore;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

abstract class DbalProjectionCheckpointStoreConformanceTestCase extends ProjectionCheckpointStoreConformanceTestCase
{
    abstract protected function createConnection(): Connection;

    protected function createProjectionCheckpointStore(): ProjectionCheckpointStore
    {
        return new DbalProjectionCheckpointStore($this->resetDatabase());
    }

    protected function reopenProjectionCheckpointStore(
        ProjectionCheckpointStore $store,
    ): ProjectionCheckpointStore {
        unset($store);

        return new DbalProjectionCheckpointStore($this->createConnection());
    }

    public function test_that_concurrent_workers_cannot_regress_committed_progress(): void
    {
        $setupConnection = $this->resetDatabase();
        new DbalProjectionCheckpointStore($setupConnection)->save('billing.revenue-summary', 41);
        $setupConnection->close();
        $coordinationPath = sprintf(
            '%s/fight-common-projection-checkpoint-race-%s',
            sys_get_temp_dir(),
            bin2hex(random_bytes(8)),
        );
        mkdir($coordinationPath);
        $workerProcessIds = [];

        try {
            $lowerWorker = $this->forkCheckpointWorker($coordinationPath, 'lower', 7);
            $workerProcessIds[$lowerWorker] = true;
            $higherWorker = $this->forkCheckpointWorker($coordinationPath, 'higher', 13);
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
            $store = new DbalProjectionCheckpointStore($this->createConnection());
            self::assertSame(253, $store->load('orders.order-summary'));
            self::assertSame(41, $store->load('billing.revenue-summary'));
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

    protected function resetDatabase(): Connection
    {
        $connection = $this->createConnection();
        $connection->executeStatement('DROP TABLE IF EXISTS projection_checkpoints');

        $schema = new DbalProjectionCheckpointStoreSchema();
        $schema->install($connection);
        $schema->install($connection);

        self::assertTrue($connection->createSchemaManager()->tablesExist([
            'projection_checkpoints',
        ]));

        return $connection;
    }

    private function forkCheckpointWorker(string $coordinationPath, string $worker, int $offset): int
    {
        $processId = pcntl_fork();

        if (-1 === $processId) {
            throw new RuntimeException(sprintf('Unable to fork the %s checkpoint worker.', $worker));
        }

        if (0 !== $processId) {
            return $processId;
        }

        $checkpointStore = new DbalProjectionCheckpointStore($this->createConnection());
        $failures = [];

        for ($round = 1; $round <= 24; ++$round) {
            file_put_contents(sprintf('%s/%s-ready-%d', $coordinationPath, $worker, $round), 'ready');
            $this->waitForFile(sprintf('%s/go-%d', $coordinationPath, $round));

            try {
                $checkpointStore->save('orders.order-summary', ($round * 10) + $offset);
            } catch (InvalidArgumentException) {
                // A lower worker may observe that the higher position already won.
            } catch (Throwable $exception) {
                $failures[] = $exception::class . ': ' . $exception->getMessage();
            }

            file_put_contents(sprintf('%s/%s-done-%d', $coordinationPath, $worker, $round), 'done');
        }

        file_put_contents(sprintf('%s/%s-result', $coordinationPath, $worker), implode("\n", $failures));
        exit(0);
    }

    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 10;

        while (!file_exists($path)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException(sprintf('Timed out waiting for checkpoint race signal %s.', $path));
            }

            usleep(1_000);
        }
    }
}
