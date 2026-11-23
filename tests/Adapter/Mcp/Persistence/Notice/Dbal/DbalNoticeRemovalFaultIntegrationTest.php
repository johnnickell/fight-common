<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRecoveryReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRepository;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTransaction;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeRemovalSelection;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversNothing]
final class DbalNoticeRemovalFaultIntegrationTest extends UnitTestCase
{
    #[DataProvider('faults')]
    public function test_that_removal_faults_leave_only_committed_evidence_visible_and_retry_never_replays_success(string $backend, string $stage): void
    {
        $directory = null;
        if ($backend === 'sqlite') {
            $directory = sys_get_temp_dir().'/fight-removal-fault-'.bin2hex(random_bytes(8));
            self::assertTrue(mkdir($directory, 0700));
            $params = ['driver' => 'pdo_sqlite', 'path' => $directory.'/notice.sqlite'];
        } else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual disposable server database required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $writer = DriverManager::getConnection($params);
        $observer = DriverManager::getConnection($params);
        $tables = new DbalNoticeTables('RemovalFaultState', 'RemovalFaultRecords', 'RemovalFaultRelations', 'RemovalFaultComparisons');
        $platform = $writer->getDatabasePlatform();
        $rowTables = [$tables->comparisonTable(), $tables->relationshipTable(), $tables->noticeTable()];
        $installed = false;
        try {
            (new DbalNoticeSchema($tables))->install($writer);
            $installed = true;
            $seedTransaction = new DbalNoticeTransaction($writer, $tables);
            $seedRepository = new DbalNoticeRepository($seedTransaction);
            $seedTransaction->commitTransactional(static function () use ($seedRepository): void {
                foreach (['notice-1', 'notice-2', 'survivor'] as $id) {
                    $seedRepository->append(Notice::fromArray(NoticeFixture::data(['id' => $id])), '2026-10-25T01:02:04.000000Z');
                }
            });
            $reader = new DbalNoticeRecoveryReader($observer, $tables);
            $selection = NoticeRemovalSelection::fromArray([
                'version'   => 1,
                'namespace' => $reader->readWindow('scope', 0, 100)->namespace(),
                'targets'   => [
                    ['scope' => 'scope', 'producer' => 'producer', 'id' => 'notice-1', 'position' => 1],
                    ['scope' => 'scope', 'producer' => 'producer', 'id' => 'notice-2', 'position' => 2]
                ]
            ]);

            $transaction = new DbalNoticeTransaction($this->faultingConnection($writer, $stage), $tables);
            $repository = new DbalNoticeRepository($transaction);
            $uncertain = in_array($stage, ['commit-before', 'commit-after'], true);
            try {
                $transaction->commitTransactional(static fn () => $repository->remove($selection));
                self::fail('Removal must not acknowledge success after a storage fault.');
            } catch (NoticeStorageFailed $failure) {
                self::assertSame($uncertain, $failure->outcomeUncertain());
                self::assertSame('Notice storage failed.', $failure->getMessage());
            }
            self::assertSame($uncertain, $transaction->isClosed());
            self::assertFalse($writer->isTransactionActive());
            $committed = $stage === 'commit-after';
            $expectedPositions = $committed ? [3] : [1, 2, 3];
            $window = $reader->readWindow('scope', 0, 100);
            self::assertSame($expectedPositions, array_map(static fn ($row) => $row->position(), $window->notices()));
            self::assertSame($committed ? 2 : 0, $window->lostThrough());
            self::assertSame(3, $window->highWater());
            foreach ($rowTables as $table) {
                self::assertSame($expectedPositions, array_map('intval', $observer->fetchFirstColumn('SELECT position FROM '.$table->quoted($platform).' ORDER BY position')));
            }

            try {
                $seedTransaction->commitTransactional(static fn () => $seedRepository->remove($selection));
                self::assertFalse($committed, 'A committed removal cannot be acknowledged again.');
            } catch (NoticeRemovalRejected $failure) {
                self::assertTrue($committed, 'A proven rollback must leave the selection reusable.');
                self::assertSame('Notice removal rejected.', $failure->getMessage());
            }
            $window = $reader->readWindow('scope', 0, 100);
            self::assertSame([3], array_map(static fn ($row) => $row->position(), $window->notices()));
            self::assertSame(2, $window->lostThrough());
        } finally {
            $observer->close();
            try {
                if ($installed) {
                    foreach ([...$rowTables, $tables->stateTable()] as $table) {
                        $writer->executeStatement('DROP TABLE IF EXISTS '.$table->quoted($platform));
                    }
                }
            } finally {
                $writer->close();
                if ($directory !== null) {
                    $this->removeTemporaryDirectory($directory, 'fight-removal-fault-');
                }
            }
        }
    }

    public static function faults(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            foreach (['delete-1', 'delete-2', 'loss', 'commit-before', 'commit-after'] as $stage) {
                yield $backend.'-'.$stage => [$backend, $stage];
            }
        }
    }

    private function faultingConnection(Connection $writer, string $stage): Connection
    {
        $proxy = $this->mock(Connection::class);
        foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'isAutoCommit', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'rollBack'] as $method) {
            $proxy->shouldReceive($method)->andReturnUsing(static fn (...$args) => $writer->$method(...$args));
        }
        $proxy->shouldReceive('fetchAssociative')->andReturnUsing(static fn (...$args) => $writer->fetchAssociative(...$args));
        $failure = new class ('Injected private diagnostic.') extends RuntimeException implements Exception {};
        $deletes = 0;
        $proxy->shouldReceive('delete')->andReturnUsing(static function (...$args) use ($writer, $stage, $failure, &$deletes) {
            $result = $writer->delete(...$args);
            if ($stage === 'delete-'.(++$deletes)) {
                throw $failure;
            }

            return $result;
        });
        $proxy->shouldReceive('update')->andReturnUsing(static function (...$args) use ($writer, $stage, $failure) {
            $result = $writer->update(...$args);
            if ($stage === 'loss') {
                throw $failure;
            }

            return $result;
        });
        $proxy->shouldReceive('commit')->andReturnUsing(static function () use ($writer, $stage, $failure): void {
            if ($stage === 'commit-after') {
                $writer->commit();
            }
            throw $failure;
        });

        return $proxy;
    }
}
