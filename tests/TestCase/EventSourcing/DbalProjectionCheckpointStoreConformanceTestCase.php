<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStoreSchema;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Common\Application\EventSourcing\ProjectionCheckpointStore;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

abstract class DbalProjectionCheckpointStoreConformanceTestCase extends ProjectionCheckpointStoreConformanceTestCase
{
    private string $tableName = 'projection_checkpoints';

    abstract protected function createConnection(): Connection;

    public static function raceSelections(): iterable
    {
        yield 'default saves' => ['projection_checkpoints', 'save'];
        yield 'configured saves' => ['Order', 'save'];
        yield 'default resets' => ['projection_checkpoints', 'reset'];
        yield 'configured resets' => ['Order', 'reset'];
    }

    public function test_that_configured_storage_preserves_the_complete_lifecycle_and_populated_defaults(): void
    {
        $this->tableName = 'Order';
        $this->test_that_checkpoints_follow_the_durable_lifecycle_contract();
        $this->assertDefaultStorageUntouched();
    }

    public function test_that_configuration_neither_installs_nor_falls_back_to_defaults(): void
    {
        $connection = $this->resetDatabase();
        new DbalProjectionCheckpointStore($connection)->save('orders.order-summary', 99);
        $selectedTable = new DbalTableName('Order');
        $connection->executeStatement('DROP TABLE IF EXISTS '.$selectedTable->quoted($connection->getDatabasePlatform()));
        $checkpointStore = new DbalProjectionCheckpointStore($connection, $selectedTable);
        $checkpointSchema = new DbalProjectionCheckpointStoreSchema($selectedTable);
        $checkpointSchema->schema();
        self::assertNotContains('Order', $connection->createSchemaManager()->listTableNames());

        $this->assertStorageOperationsFail($checkpointStore);
        self::assertSame(99, new DbalProjectionCheckpointStore($connection)->load('orders.order-summary'));

        $connection->executeStatement(
            'CREATE TABLE '.$selectedTable->quoted($connection->getDatabasePlatform()).' (wrong_column INT)',
        );
        $checkpointSchema->install($connection);
        $this->assertStorageOperationsFail($checkpointStore);
        self::assertSame(99, new DbalProjectionCheckpointStore($connection)->load('orders.order-summary'));
    }

    protected function createProjectionCheckpointStore(): ProjectionCheckpointStore
    {
        return new DbalProjectionCheckpointStore($this->resetDatabase(), new DbalTableName($this->tableName));
    }

    protected function reopenProjectionCheckpointStore(
        ProjectionCheckpointStore $store,
    ): ProjectionCheckpointStore {
        unset($store);

        return new DbalProjectionCheckpointStore($this->createConnection(), new DbalTableName($this->tableName));
    }

    #[DataProvider('raceSelections')]
    public function test_that_concurrent_workers_preserve_selected_progress(string $tableName, string $operation): void
    {
        $this->tableName = $tableName;
        $setupConnection = $this->resetDatabase();
        new DbalProjectionCheckpointStore($setupConnection, new DbalTableName($tableName))->save(
            'billing.revenue-summary', 41,
        );
        $setupConnection->close();
        $coordinationPath = sprintf(
            '%s/fight-common-projection-checkpoint-race-%s',
            sys_get_temp_dir(),
            bin2hex(random_bytes(8)),
        );
        mkdir($coordinationPath);
        $workerProcessIds = [];

        try {
            $lowerWorker = $this->forkCheckpointWorker($coordinationPath, 'lower', 7, $operation);
            $workerProcessIds[$lowerWorker] = true;
            $higherWorker = $this->forkCheckpointWorker($coordinationPath, 'higher', 13, $operation);
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
            $checkpointStore = new DbalProjectionCheckpointStore($this->createConnection(), new DbalTableName($tableName));
            if ('save' === $operation) {
                self::assertSame(253, $checkpointStore->load('orders.order-summary'));
            } else {
                for ($round = 1; $round <= 24; ++$round) {
                    self::assertSame(0, $checkpointStore->load('reset-'.$round));
                }
            }
            self::assertSame(41, $checkpointStore->load('billing.revenue-summary'));
            if ('projection_checkpoints' !== $tableName) {
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

    protected function resetDatabase(): Connection
    {
        $connection = $this->createConnection();
        foreach (['projection_checkpoints', 'Order'] as $tableName) {
            $table = new DbalTableName($tableName);
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->quoted($connection->getDatabasePlatform()));
        }

        $schema = new DbalProjectionCheckpointStoreSchema(new DbalTableName($this->tableName));
        $schema->install($connection);
        $schema->install($connection);

        if ('projection_checkpoints' !== $this->tableName) {
            (new DbalProjectionCheckpointStoreSchema())->install($connection);
            $defaultCheckpointStore = new DbalProjectionCheckpointStore($connection);
            foreach (['orders.order-summary', 'billing.revenue-summary', 'inventory.stock-summary'] as $projectorName) {
                $defaultCheckpointStore->save($projectorName, 999);
            }
        }

        self::assertSame(0, new DbalProjectionCheckpointStore(
            $connection, new DbalTableName($this->tableName),
        )->load('orders.order-summary'));

        return $connection;
    }

    private function forkCheckpointWorker(string $coordinationPath, string $worker, int $offset, string $operation): int
    {
        $processId = pcntl_fork();

        if (-1 === $processId) {
            throw new RuntimeException(sprintf('Unable to fork the %s checkpoint worker.', $worker));
        }

        if (0 !== $processId) {
            return $processId;
        }

        $checkpointStore = new DbalProjectionCheckpointStore(
            $this->createConnection(), new DbalTableName($this->tableName),
        );
        $failures = [];

        for ($round = 1; $round <= 24; ++$round) {
            file_put_contents(sprintf('%s/%s-ready-%d', $coordinationPath, $worker, $round), 'ready');
            $this->waitForFile(sprintf('%s/go-%d', $coordinationPath, $round));

            try {
                if ('save' === $operation) {
                    $checkpointStore->save('orders.order-summary', ($round * 10) + $offset);
                } else {
                    $checkpointStore->reset('reset-'.$round);
                }
            } catch (InvalidArgumentException $exception) {
                // Only a lower save may observe that the higher position already won.
                if ('save' !== $operation) {
                    $failures[] = $exception::class.': '.$exception->getMessage();
                }
            } catch (Throwable $exception) {
                $failures[] = $exception::class . ': ' . $exception->getMessage();
            }

            file_put_contents(sprintf('%s/%s-done-%d', $coordinationPath, $worker, $round), 'done');
        }

        file_put_contents(sprintf('%s/%s-result', $coordinationPath, $worker), implode("\n", $failures));
        exit(0);
    }

    private function assertDefaultStorageUntouched(): void
    {
        $connection = $this->createConnection();
        $rows = $connection->fetchAllAssociative(
            'SELECT projector_name, global_position FROM projection_checkpoints ORDER BY projector_name',
        );
        self::assertSame([
            ['projector_name' => 'billing.revenue-summary', 'global_position' => 999],
            ['projector_name' => 'inventory.stock-summary', 'global_position' => 999],
            ['projector_name' => 'orders.order-summary', 'global_position' => 999],
        ], array_map(static fn(array $row): array => [
            'projector_name' => $row['projector_name'],
            'global_position' => (int) $row['global_position'],
        ], $rows));
    }

    private function assertStorageOperationsFail(DbalProjectionCheckpointStore $checkpointStore): void
    {
        foreach (['load', 'save', 'reset'] as $operation) {
            try {
                if ('save' === $operation) {
                    $checkpointStore->save('orders.order-summary', 1);
                } else {
                    $checkpointStore->$operation('orders.order-summary');
                }
                self::fail('Expected missing/incompatible selected storage to fail: '.$operation);
            } catch (Exception) {
                self::assertTrue(true);
            }
        }
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
