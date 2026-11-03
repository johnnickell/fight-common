<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Persistence\Doctrine;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Error;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork;
use Fight\Common\Adapter\Persistence\Doctrine\Exception\DbalTransactionOutcomeUncertain;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\Repository\TransactionalUnitOfWorkConformanceTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;

#[CoversClass(DbalTransactionalUnitOfWork::class)]
#[CoversClass(DbalTransactionOutcomeUncertain::class)]
final class DbalTransactionalUnitOfWorkTest extends TransactionalUnitOfWorkConformanceTestCase
{
    public function test_that_success_and_callback_failure_preserve_real_atomicity_and_reuse(): void
    {
        $connection = $this->database();
        $transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
        $result = new stdClass();
        self::assertSame($result, $transactionalUnitOfWork->commitTransactional(static function () use ($connection, $result): object {
            $connection->insert('consumer_work', ['id' => 1]);
            return $result;
        }));
        $failure = new Error('callback failed');
        try {
            $transactionalUnitOfWork->commitTransactional(static function () use ($connection, $failure): never {
                $connection->insert('consumer_work', ['id' => 2]);
                throw $failure;
            });
            self::fail('Expected the original callback failure.');
        } catch (Error $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame([1], $connection->fetchFirstColumn('SELECT id FROM consumer_work'));
        self::assertFalse($connection->isTransactionActive());
        self::assertFalse($transactionalUnitOfWork->isClosed());
        self::assertNull($transactionalUnitOfWork->commitTransactional(static fn () => null));
    }

    public function test_that_a_default_lazy_connection_commits_before_returning_to_an_independent_reader(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fight-dbal-');
        self::assertIsString($path);
        $reader = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        try {
            $reader->executeStatement('CREATE TABLE consumer_work (id INTEGER)');
            self::assertFalse($connection->isConnected());
            $transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
            self::assertSame('committed', $transactionalUnitOfWork->commitTransactional(static function () use ($connection): string {
                $connection->insert('consumer_work', ['id' => 184]);
                return 'committed';
            }));
            self::assertFalse($connection->isTransactionActive());
            self::assertSame([184], $reader->fetchFirstColumn('SELECT id FROM consumer_work'));
            self::assertFalse($transactionalUnitOfWork->isClosed());
            $connection->close();
            self::assertSame([184], $reader->fetchFirstColumn('SELECT id FROM consumer_work'));
        } finally {
            $connection->close();
            $reader->close();
            unlink($path);
        }
    }

    #[DataProvider('incompatibleConnectionStates')]
    public function test_that_auto_commit_off_rejects_before_callback_without_changing_caller_state(string $state): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fight-dbal-');
        self::assertIsString($path);
        $configuration = new Configuration();
        $configuration->setAutoCommit($state === 'established idle');
        $reader = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path], $configuration);
        try {
            $reader->executeStatement('CREATE TABLE consumer_work (id INTEGER)');
            if ($state === 'established idle') {
                $connection->fetchOne('SELECT 1');
                $connection->setAutoCommit(false);
            }
            if ($state === 'caller work') {
                $connection->insert('consumer_work', ['id' => 42]);
            }
            self::assertFalse($connection->isAutoCommit());
            self::assertSame($state !== 'lazy', $connection->isConnected());
            self::assertSame($state === 'caller work', $connection->isTransactionActive());
            $transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
            $invoked = false;
            $rejected = false;
            try {
                $transactionalUnitOfWork->commitTransactional(static function () use ($connection, &$invoked): void {
                    $invoked = true;
                    $connection->insert('consumer_work', ['id' => 184]);
                });
            } catch (LogicException) {
                $rejected = true;
            }
            self::assertFalse($invoked, 'Incompatible connection must reject before invoking the callback.');
            self::assertTrue($rejected);
            self::assertFalse($transactionalUnitOfWork->isClosed());
            self::assertFalse($connection->isAutoCommit());
            self::assertSame($state !== 'lazy', $connection->isConnected());
            self::assertSame($state === 'caller work', $connection->isTransactionActive());
            self::assertSame([], $reader->fetchFirstColumn('SELECT id FROM consumer_work'));
            if ($state === 'caller work') {
                self::assertSame([42], $connection->fetchFirstColumn('SELECT id FROM consumer_work'));
                $connection->commit();
                self::assertSame([42], $reader->fetchFirstColumn('SELECT id FROM consumer_work'));
            }
        } finally {
            $connection->close();
            $reader->close();
            unlink($path);
        }
    }

    public static function incompatibleConnectionStates(): iterable
    {
        yield 'lazy' => ['lazy'];
        yield 'established idle' => ['established idle'];
        yield 'caller work' => ['caller work'];
    }

    public function test_that_caller_owned_work_is_neither_invoked_committed_nor_rolled_back(): void
    {
        $connection = $this->database();
        $transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
        $connection->beginTransaction();
        $connection->insert('consumer_work', ['id' => 42]);
        try {
            $transactionalUnitOfWork->commitTransactional(static function (): never {
                self::fail('Caller transaction must reject before callback.');
            });
            self::fail('Expected caller transaction rejection.');
        } catch (LogicException) {
            self::assertTrue($connection->isTransactionActive());
            self::assertSame(42, (int) $connection->fetchOne('SELECT id FROM consumer_work'));
            self::assertFalse($transactionalUnitOfWork->isClosed());
        }
        $connection->rollBack();
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM consumer_work'));
    }

    #[DataProvider('uncertainFailures')]
    public function test_that_uncertain_database_outcomes_close_the_boundary_and_keep_the_original_cause(
        string $stage,
        bool $activeAfterCommit,
        bool $rollbackFails
    ): void {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->andReturn(false, $activeAfterCommit);
        $connection->shouldReceive('isAutoCommit')->once()->andReturnTrue();
        $failure = new RuntimeException('lost acknowledgment');
        if ($stage === 'begin') {
            $connection->shouldReceive('beginTransaction')->once()->andThrow($failure);
        } else {
            $connection->shouldReceive('beginTransaction')->once();
            if ($stage === 'commit') {
                $connection->shouldReceive('commit')->once()->andThrow($failure);
            }
            if ($stage === 'rollback' || $activeAfterCommit) {
                $rollback = $connection->shouldReceive('rollBack')->once();
                if ($rollbackFails) {
                    $rollback->andThrow(new RuntimeException('cleanup failed'));
                }
            }
        }
        $transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
        try {
            $transactionalUnitOfWork->commitTransactional(static function () use ($stage, $failure): void {
                if ($stage === 'rollback') {
                    throw $failure;
                }
            });
            self::fail('Expected uncertain transaction outcome.');
        } catch (DbalTransactionOutcomeUncertain $caught) {
            self::assertSame($failure, $caught->getPrevious());
            self::assertSame($failure, $caught->originalFailure());
        }
        self::assertTrue($transactionalUnitOfWork->isClosed());
        $this->expectException(LogicException::class);
        $transactionalUnitOfWork->commitTransactional(static fn () => null);
    }

    public static function uncertainFailures(): iterable
    {
        yield 'begin' => ['begin', false, false];
        yield 'commit without active transaction' => ['commit', false, false];
        yield 'commit with cleanup' => ['commit', true, false];
        yield 'commit with failed cleanup' => ['commit', true, true];
        yield 'failed rollback' => ['rollback', false, true];
    }

    public function test_that_database_callback_failures_are_not_reclassified_after_confirmed_rollback(): void
    {
        $connection = $this->database();
        $transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
        try {
            $transactionalUnitOfWork->commitTransactional(static fn () => $connection->executeStatement('INSERT INTO missing_table VALUES (1)'));
            self::fail('Expected database callback failure.');
        } catch (Exception) {
            self::assertFalse($transactionalUnitOfWork->isClosed());
            self::assertFalse($connection->isTransactionActive());
        }
    }

    public function test_that_failed_commit_cleanup_inspection_still_reports_the_original_uncertain_outcome(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->once()->andReturnFalse();
        $connection->shouldReceive('isAutoCommit')->once()->andReturnTrue();
        $connection->shouldReceive('isTransactionActive')->once()->andThrow(new RuntimeException('inspection failed'));
        $connection->shouldReceive('beginTransaction')->once();
        $failure = new RuntimeException('commit failed');
        $connection->shouldReceive('commit')->once()->andThrow($failure);
        $transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
        try {
            $transactionalUnitOfWork->commitTransactional(static fn () => null);
            self::fail('Expected uncertain outcome.');
        } catch (DbalTransactionOutcomeUncertain $caught) {
            self::assertSame($failure, $caught->originalFailure());
            self::assertTrue($transactionalUnitOfWork->isClosed());
        }
    }

    protected function createTransactionalUnitOfWork(): TransactionalUnitOfWork
    {
        return new DbalTransactionalUnitOfWork($this->database());
    }

    private function database(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE consumer_work (id INTEGER)');
        return $connection;
    }
}
