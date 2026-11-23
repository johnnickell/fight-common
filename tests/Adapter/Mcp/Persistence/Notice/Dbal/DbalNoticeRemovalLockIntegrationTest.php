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
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Domain\Mcp\Notice\Command\RecordNotice;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\RecoverNotices;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRecoveryFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class DbalNoticeRemovalLockIntegrationTest extends UnitTestCase
{
    #[DataProvider('orders')]
    #[DataProvider('readerOrders')]
    public function test_that_recording_and_removal_serialize_retries_loss_and_current_admission(
        string $backend,
        array $names,
        string $mode,
        array $positions,
        int $lost,
        int $high,
        string $firstStatus = 'ok',
        string $secondStatus = 'ok',
        ?string $readOrder = null,
        bool $continuationRace = false
    ): void {
        $directory = sys_get_temp_dir().'/fight-notice-removal-lock-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $params = $this->parameters($backend, $directory);
        $connection = DriverManager::getConnection($params);
        $tables = new DbalNoticeTables(...$names);
        $platform = $connection->getDatabasePlatform();
        $related = array_map(static fn ($table) => $table->quoted($platform), [
            $tables->comparisonTable(), $tables->relationshipTable(), $tables->noticeTable()
        ]);
        $owned = [...$related, $tables->stateTable()->quoted($platform)];
        $config = $directory.'/config.json';
        $workerDirectory = dirname(__DIR__, 5).'/TestCase/Mcp/Notice/';
        $recordOnly = $readOrder === 'record-first';
        $firstScript = match ($readOrder) {
            'record-first' => 'record-worker.php',
            'recover-first' => 'recovery-worker.php',
            default => 'removal-worker.php'
        };
        $secondScript = in_array($readOrder, ['record-first', 'remove-first'], true) ? 'recovery-worker.php' : 'removal-worker.php';
        $first = new Process([PHP_BINARY, $workerDirectory.$firstScript, $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, $workerDirectory.$secondScript, $config, 'second'], timeout: 25);
        try {
            foreach ($owned as $table) {
                $connection->executeStatement("DROP TABLE IF EXISTS $table");
            }
            (new DbalNoticeSchema($tables))->install($connection);
            $fixture = new NoticeRemovalFixture($connection, $tables);
            $seedIds = $recordOnly ? [] : ['notice-1', 'survivor'];
            foreach ($seedIds as $id) {
                $fixture->recording->recordNoticeHandler->handle(CommandMessage::create(RecordNotice::fromArray(NoticeFixture::data(['id' => $id]))));
            }
            $observer = new DbalNoticeRecoveryReader($connection, $tables);
            $query = new NoticeRecoveryFixture($connection, tables: $tables)->queryBus;
            $selection = NoticeSelection::create(['scope']);
            $token = $continuationRace && !$recordOnly ? $query->fetch(new RecoverNotices($selection, batchSize: 1))->toArray()['continuation'] : null;
            file_put_contents($config, json_encode([
                'params' => $params, 'mode' => $mode, 'tables' => $names, 'direct' => true,
                'after' => $recordOnly ? 0 : 1,
                'selection' => $recordOnly ? null : $fixture->selection([
                    $readOrder === null ? NoticeRemovalFixture::target() : NoticeRemovalFixture::target(2, 'survivor')
                ])->toArray()
            ] + ($continuationRace ? ['continuationRace' => ['token' => $token]] : []), JSON_THROW_ON_ERROR));
            $first->start();
            $this->waitFor($first, $directory.($recordOnly ? '/first-precommit' : '/first-locked'));
            $second->start();
            $this->waitFor($second, $directory.'/second-started');
            usleep(200000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted');
            self::assertSame($recordOnly ? [] : [1, 2], array_map(static fn ($row) => $row->position(), $observer->readAfter('scope', 0, 100)));
            if (str_contains($mode, 'revoked')) {
                touch($directory.'/revoked');
            }
            touch($directory.'/release');
            self::assertSame(0, $first->wait(), $first->getErrorOutput());
            self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $one = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $two = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($firstStatus, $one['status']);
            self::assertSame($secondStatus, $two['status']);
            self::assertSame($secondScript === 'recovery-worker.php' || $mode === 'remove-first-window' ? 0 : 1, $two['recordAdmissions'] + $two['removalAdmissions']);
            $window = $observer->readWindow('scope', 0, 100);
            $rows = $window->notices();
            self::assertSame($positions, array_map(static fn ($row) => $row->position(), $rows));
            if ($readOrder === null) {
                self::assertSame('survivor', $rows[array_search(2, $positions, true)]->notice()->id());
            } else {
                self::assertSame(['notice-1'], array_map(static fn ($row) => $row->notice()->id(), $rows));
                $expectedSnapshot = match ($readOrder) {
                    'record-first' => ['positions' => [1], 'high' => 1, 'lost' => 0],
                    'remove-first' => ['positions' => [], 'high' => 2, 'lost' => 2],
                    'recover-first' => ['positions' => [2], 'high' => 2, 'lost' => 0]
                };
                if ($continuationRace) {
                    $batch = $two['batch'];
                    self::assertSame($recordOnly ? 'complete' : 'incomplete', $batch['status']);
                    self::assertSame($recordOnly ? ['notice-1'] : [], array_column($batch['notices'], 'id'));
                } else {
                    self::assertSame($expectedSnapshot, ($readOrder === 'recover-first' ? $one : $two)['window']);
                }
            }
            self::assertSame($lost, $window->lostThrough());
            self::assertSame($high, $window->highWater());
            if ($mode === 'remove-first-conflicting') {
                self::assertSame('changed', $rows[1]->notice()->toArray()['topic']);
            }
            if ($mode === 'remove-first-window' && $readOrder === null) {
                self::assertSame(['positions' => [2], 'high' => 2, 'lost' => 1], $two['window']);
            }
            self::assertSame([], $observer->readAfter('scope', $high, 100));
            foreach ($related as $table) {
                self::assertSame($positions, array_map('intval', $connection->fetchFirstColumn("SELECT position FROM $table ORDER BY position")));
            }
            if ($continuationRace) {
                if ($recordOnly) {
                    $fixture->recording->recordNoticeHandler->handle(CommandMessage::create(RecordNotice::fromArray(NoticeFixture::data(['id' => 'notice-2']))));
                }
                $next = $query->fetch(new RecoverNotices($selection, $two['batch']['continuation'], 1))->toArray();
                self::assertSame($recordOnly ? 'complete' : 'incomplete', $next['status']);
                self::assertSame($recordOnly ? ['notice-2'] : [], array_column($next['notices'], 'id'));
            }
        } finally {
            touch($directory.'/release');
            $first->stop();
            $second->stop();
            try {
                foreach ($owned as $table) {
                    $connection->executeStatement("DROP TABLE IF EXISTS $table");
                }
            } finally {
                $connection->close();
                $this->removeTemporaryDirectory($directory, 'fight-notice-removal-lock-');
            }
        }
    }

    public static function orders(): iterable
    {
        $partial = ['stateTable' => 'RemovalLockState', 'comparisonTable' => 'RemovalLockComparisons'];
        $configured = [...$partial, 'noticeTable' => 'Order', 'relationshipTable' => 'RemovalLockRelations'];
        yield 'sqlite-remove-first' => ['sqlite', [], 'remove-first-distinct', [2, 3], 1, 3];
        yield 'mysql-remove-first' => ['mysql', $partial, 'remove-first-distinct', [2, 3], 1, 3];
        yield 'mysql-record-first' => ['mysql', $configured, 'record-first-remove', [2, 3], 1, 3];
        yield 'postgresql-remove-first' => ['postgresql', $configured, 'remove-first-distinct', [2, 3], 1, 3];
        yield 'postgresql-record-first' => ['postgresql', [], 'record-first-remove', [2, 3], 1, 3];
        yield 'sqlite-identical-retry' => ['sqlite', [], 'remove-first-identical', [2, 3], 1, 3];
        yield 'mysql-conflicting-retry' => ['mysql', $partial, 'remove-first-conflicting', [2, 3], 1, 3];
        yield 'postgresql-repeat-removal' => ['postgresql', $configured, 'remove-first-repeat', [2], 1, 2, 'ok', NoticeRemovalRejected::class];
        yield 'sqlite-retry-first' => ['sqlite', $configured, 'retry-first-remove', [2], 1, 2];
        yield 'mysql-conflict-first' => ['mysql', [], 'conflict-first-remove', [2], 1, 2, NoticeConflict::class];
        yield 'postgresql-rollback' => ['postgresql', $partial, 'remove-rollback-retry', [1, 2], 0, 2, NoticeStorageFailed::class];
        yield 'sqlite-revoked-record' => ['sqlite', $partial, 'remove-first-revoked-record', [2], 1, 2, 'ok', NoticeRejected::class];
        yield 'mysql-revoked-remove' => ['mysql', $configured, 'record-first-revoked-remove', [1, 2, 3], 0, 3, 'ok', NoticeRemovalRejected::class];
        yield 'postgresql-waiting-window' => ['postgresql', [], 'remove-first-window', [2], 1, 2];
    }

    public static function readerOrders(): iterable
    {
        $partial = ['stateTable' => 'RecoveryLockState', 'comparisonTable' => 'RecoveryLockComparisons'];
        $configured = [...$partial, 'noticeTable' => 'Order', 'relationshipTable' => 'RecoveryLockRelations'];
        yield 'sqlite-read-after-record' => ['sqlite', [], 'distinct', [1], 0, 1, 'recorded', 'ok', 'record-first', true];
        yield 'mysql-read-after-remove' => ['mysql', $configured, 'remove-first-window', [1], 2, 2, 'ok', 'ok', 'remove-first', true];
        yield 'mysql-read-after-record' => ['mysql', $partial, 'distinct', [1], 0, 1, 'recorded', 'ok', 'record-first'];
        yield 'postgresql-read-after-record' => ['postgresql', $configured, 'distinct', [1], 0, 1, 'recorded', 'ok', 'record-first'];
        yield 'sqlite-read-after-remove' => ['sqlite', $partial, 'remove-first-window', [1], 2, 2, 'ok', 'ok', 'remove-first'];
        yield 'postgresql-read-after-remove' => ['postgresql', [], 'remove-first-window', [1], 2, 2, 'ok', 'ok', 'remove-first'];
        yield 'sqlite-recover-before-remove' => ['sqlite', $configured, 'record-first-remove', [1], 2, 2, 'ok', 'ok', 'recover-first'];
        yield 'mysql-recover-before-remove' => ['mysql', [], 'record-first-remove', [1], 2, 2, 'ok', 'ok', 'recover-first'];
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

    private function waitFor(Process $worker, string $path): void
    {
        $deadline = hrtime(true) + 15000000000;
        while (!is_file($path)) {
            if (!$worker->isRunning() || hrtime(true) >= $deadline) {
                self::fail('Notice worker did not reach '.$path.': '.$worker->getErrorOutput().$worker->getOutput());
            }
            usleep(10000);
        }
    }
}
