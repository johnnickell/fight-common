<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRecoveryReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRejected;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Domain\Mcp\Notice\Command\RecordNotice;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class DbalNoticeRecordingLockIntegrationTest extends UnitTestCase
{
    #[DataProvider('writerOrders')]
    public function test_that_a_waiting_notice_writer_uses_only_committed_positions_and_current_admission(
        string $backend,
        array $selection,
        string $mode,
        string $status,
        array $events,
        array $ids
    ): void {
        $directory = sys_get_temp_dir().'/fight-notice-writers-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $params = $this->parameters($backend, $directory);
        $connection = DriverManager::getConnection($params);
        $tables = new DbalNoticeTables(...$selection);
        $platform = $connection->getDatabasePlatform();
        $config = $directory.'/config.json';
        $worker = dirname(__DIR__, 5).'/TestCase/Mcp/Notice/record-worker.php';
        $first = new Process([PHP_BINARY, $worker, $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, $worker, $config, 'second'], timeout: 25);
        $owned = array_map(static fn ($table) => $table->quoted($platform), [
            $tables->comparisonTable(), $tables->relationshipTable(), $tables->noticeTable(), $tables->stateTable()
        ]);
        try {
            foreach ($owned as $table) {
                $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
            }
            (new DbalNoticeSchema($tables))->install($connection);
            $reader = new DbalNoticeRecoveryReader($connection, $tables);
            file_put_contents($config, json_encode(['params' => $params, 'mode' => $mode, 'tables' => $selection, 'direct' => true], JSON_THROW_ON_ERROR));
            $first->start();
            $this->waitForWriter($directory.'/first-precommit', $first);
            self::assertSame('1', file_get_contents($directory.'/first-precommit'));
            $second->start();
            $this->waitForWriter($directory.'/second-started', $second);
            usleep(200000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted');
            self::assertSame([], $reader->readAfter('scope', 0, 100));
            if ($mode === 'revoked') {
                touch($directory.'/revoked');
            }
            touch($directory.'/release');
            self::assertSame(0, $first->wait(), $first->getErrorOutput());
            self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $one = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $two = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($mode === 'rollback' ? NoticeStorageFailed::class : 'recorded', $one['status']);
            self::assertSame($events[0], $one['events']);
            self::assertSame(['status' => $status, 'events' => $events[1], 'admissions' => 1, 'uncertain' => null], $two);
            $rows = $reader->readAfter('scope', 0, 100);
            self::assertSame($ids, array_map(static fn ($row) => $row->notice()->id(), $rows));
            self::assertSame(range(1, count($ids)), array_map(static fn ($row) => $row->position(), $rows));
            self::assertSame('catalog/tools', $rows[0]->notice()->toArray()['topic']);
            self::assertSame([], $reader->readAfter('scope', count($ids), 100));
            if ($mode === 'rollback') {
                (new NoticeFixture($connection, $tables))->recordNoticeHandler->handle(CommandMessage::create(RecordNotice::fromArray(NoticeFixture::data())));
                $retry = $reader->readAfter('scope', 1, 100);
                self::assertCount(1, $retry);
                self::assertSame(2, $retry[0]->position());
                self::assertSame('notice-1', $retry[0]->notice()->id());
            }
        } finally {
            touch($directory.'/release');
            $first->stop();
            $second->stop();
            try {
                foreach ($owned as $table) {
                    $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
                }
            } finally {
                $connection->close();
                $this->removeTemporaryDirectory($directory, 'fight-notice-writers-');
            }
        }
    }

    public static function writerOrders(): iterable
    {
        $partial = ['stateTable' => 'LockNoticeState', 'comparisonTable' => 'LockNoticeComparisons'];
        $configured = [
            'stateTable' => 'LockNoticeState', 'noticeTable' => 'Order',
            'relationshipTable' => 'LockNoticeRelations', 'comparisonTable' => 'LockNoticeComparisons'
        ];
        yield 'sqlite-distinct' => ['sqlite', [], 'distinct', 'recorded', [1, 1], ['notice-1', 'notice-2']];
        yield 'mysql-partial-distinct' => ['mysql', $partial, 'distinct', 'recorded', [1, 1], ['notice-1', 'notice-2']];
        yield 'sqlite-duplicate' => ['sqlite', [], 'duplicate', 'recorded', [1, 0], ['notice-1']];
        yield 'mysql-partial-conflict' => ['mysql', $partial, 'conflict', NoticeConflict::class, [1, 0], ['notice-1']];
        yield 'postgresql-configured-rollback' => ['postgresql', $configured, 'rollback', 'recorded', [0, 1], ['notice-2']];
        yield 'sqlite-configured-revoked' => ['sqlite', $configured, 'revoked', NoticeRejected::class, [1, 0], ['notice-1']];
    }

    private function parameters(string $backend, string $directory): array
    {
        if ($backend === 'sqlite') {
            return ['driver' => 'pdo_sqlite', 'path' => $directory.'/notices.sqlite'];
        }
        $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
        self::assertIsString($dsn, 'Actual disposable server database required.');

        return (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
    }

    private function waitForWriter(string $path, Process $worker): void
    {
        $deadline = hrtime(true) + 15000000000;
        while (!is_file($path)) {
            if (!$worker->isRunning() || hrtime(true) >= $deadline) {
                self::fail('Notice writer did not reach '.$path.': '.$worker->getOutput().$worker->getErrorOutput());
            }
            usleep(10000);
        }
    }
}
