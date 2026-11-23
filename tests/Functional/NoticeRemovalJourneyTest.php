<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRecoveryReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRejected;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\NoticeRemovalSelection;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class NoticeRemovalJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];
    private ?DbalNoticeTables $installedTables = null;

    #[DataProvider('routedSelection')]
    public function test_that_bounded_commands_preserve_distinct_unselected_scopes_producers_and_relationships(string $backend, DbalNoticeTables $tables): void
    {
        $connection = $this->database($backend, $tables); $fixture = new NoticeRemovalFixture($connection, $tables);
        $fixture->recording->record();
        $fixture->recording->record(['producer' => 'producer-2', 'topic' => 'other-topic']);
        $fixture->recording->record(['scope' => 'scope-2']);
        $fixture->recording->record(['id' => 'broadcast', 'relationships' => ['subject' => null, 'recipients' => [], 'author' => null, 'command' => null, 'event' => null, 'action' => null]]);
        $before = $fixture->recording->noticeRecoveryReader->readWindow('scope', 0, 100);
        $selection = $fixture->selection([NoticeRemovalFixture::target(), NoticeRemovalFixture::target(4, 'broadcast')]);
        $fixture->remove($selection); $this->assertRemovalRejected(fn () => $fixture->remove($selection));
        $fresh = new DbalNoticeRecoveryReader($this->connect($connection->getParams()), $tables);
        $window = $fresh->readWindow('scope', 0, 100);
        self::assertSame([2], array_map(static fn ($row) => $row->position(), $window->notices()));
        self::assertSame($before->notices()[1]->notice()->toArray(), $window->notices()[0]->notice()->toArray());
        self::assertSame($before->notices()[1]->recordedAt(), $window->notices()[0]->recordedAt());
        self::assertSame(3, $fresh->readAfter('scope-2', 0, 100)[0]->position());
        self::assertSame(4, $window->lostThrough()); self::assertSame(4, $window->highWater());
        self::assertFalse($window->hasUnbrokenHistoryAfter($before->namespace(), 0));
        foreach ($this->relatedTables($connection, $tables) as $table) { self::assertSame([2, 3], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table.' ORDER BY position'))); }
        // The retained comparison still conflicts; removed comparison is deliberately gone, not archived.
        try { $fixture->recording->record(['producer' => 'producer-2']); self::fail('Retained changed content conflicts.'); } catch (NoticeConflict) { self::assertTrue(true); }
        $fixture->recording->record(['topic' => 'changed-after-removal']);
        self::assertSame([5], array_map(static fn ($row) => $row->position(), $fresh->readAfter('scope', 4, 100)));
        $this->assertRemovalRejected(fn () => $fixture->remove($selection)); self::assertSame(5, $fresh->readAfter('scope', 4, 100)[0]->position());
        $fixture->allowed = false;
        foreach ([$selection, $fixture->selection([NoticeRemovalFixture::target(100, 'missing')])] as $request) {
            try { $fixture->remove($request); self::fail('Fresh permission required.'); } catch (NoticeRemovalRejected) { self::assertCount(2, $fresh->readAfter('scope', 0, 100)); }
        }
    }

    #[DataProvider('routedSelection')]
    public function test_that_routed_removal_reports_a_lost_commit_acknowledgment_without_replaying_success(string $backend, DbalNoticeTables $tables): void
    {
        $connection = $this->database($backend, $tables);
        $seed = new NoticeRemovalFixture($connection, $tables);
        $seed->recording->record();
        $seed->recording->record(['id' => 'notice-2']);
        $seed->recording->record(['id' => 'survivor']);
        $selection = $seed->selection([NoticeRemovalFixture::target(), NoticeRemovalFixture::target(2, 'notice-2')]);
        $proxy = $this->proxy($connection);
        $proxy->shouldReceive('commit')->andReturnUsing(static function () use ($connection): void {
            $connection->commit();
            throw new class ('Injected private diagnostic.') extends RuntimeException implements Exception {};
        });
        $fixture = new NoticeRemovalFixture($proxy, $tables);
        try {
            $fixture->remove($selection);
            self::fail('Fault must not acknowledge success.');
        } catch (NoticeStorageFailed $failure) {
            self::assertTrue($failure->outcomeUncertain());
            self::assertSame('Notice storage failed.', $failure->getMessage());
        }
        $fresh = new DbalNoticeRecoveryReader($this->connect($connection->getParams()), $tables);
        $this->assertRemovalRejected(fn () => $seed->remove($selection));
        $window = $fresh->readWindow('scope', 0, 100);
        self::assertSame([3], array_map(static fn ($row) => $row->position(), $window->notices()));
        self::assertSame(2, $window->lostThrough());
    }

    #[DataProvider('races')]
    public function test_that_real_recording_removal_and_retry_races_serialize_committed_survivors_and_coherent_loss(string $backend, string $mode, DbalNoticeTables $tables): void
    {
        $connection = $this->database($backend, $tables); $fixture = new NoticeRemovalFixture($connection, $tables);
        $fixture->recording->record(); $fixture->recording->record(['id' => 'survivor']);
        $selection = $fixture->selection([NoticeRemovalFixture::target()]); $directory = $this->directory();
        $config = $directory.'/config.json'; file_put_contents($config, json_encode(['params' => $connection->getParams(), 'mode' => $mode, 'selection' => $selection->toArray(), 'tables' => $this->workerTables($tables)], JSON_THROW_ON_ERROR));
        $first = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Notice/removal-worker.php', $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Notice/removal-worker.php', $config, 'second'], timeout: 25);
        try {
            $first->start(); $this->waitFor($directory.'/first-locked', $first);
            $second->start(); $this->waitFor($directory.'/second-started', $second); usleep(200000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted', 'Waiting work must authorize only after acquiring the lock.');
            $observer = new DbalNoticeRecoveryReader($this->connect($connection->getParams()), $tables);
            self::assertSame([1, 2], array_map(static fn ($row) => $row->position(), $observer->readAfter('scope', 0, 100)), 'Uncommitted removal/allocation must not escape.');
            if (str_contains($mode, 'revoked')) { file_put_contents($directory.'/revoked', 'yes'); }
            file_put_contents($directory.'/release', 'yes');
            self::assertSame(0, $first->wait(), $first->getErrorOutput()); self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $one = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR); $two = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(match ($mode) { 'conflict-first-remove' => NoticeConflict::class, 'remove-rollback-retry' => NoticeStorageFailed::class, default => 'ok' }, $one['status']);
            self::assertSame(match ($mode) { 'remove-first-revoked-record' => NoticeRejected::class, 'record-first-revoked-remove', 'remove-first-repeat' => NoticeRemovalRejected::class, default => 'ok' }, $two['status']);
            $expectedPositions = match ($mode) {
                'remove-first-identical', 'remove-first-conflicting', 'remove-first-distinct', 'record-first-remove' => [2, 3],
                'record-first-revoked-remove' => [1, 2, 3], 'remove-rollback-retry' => [1, 2], default => [2]
            };
            $window = $observer->readWindow('scope', 0, 100);
            self::assertSame($expectedPositions, array_map(static fn ($row) => $row->position(), $window->notices()));
            self::assertSame(in_array($mode, ['record-first-revoked-remove', 'remove-rollback-retry'], true) ? 0 : 1, $window->lostThrough());
            self::assertSame(in_array($mode, ['remove-first-identical', 'remove-first-conflicting', 'remove-first-distinct', 'record-first-remove', 'record-first-revoked-remove'], true) ? 3 : 2, $window->highWater());
            $survivors = array_values(array_filter($window->notices(), static fn ($row) => $row->position() === 2));
            self::assertCount(1, $survivors); self::assertSame('survivor', $survivors[0]->notice()->id());
            if ($mode === 'remove-first-conflicting') { self::assertSame('changed', $window->notices()[1]->notice()->toArray()['topic']); }
            if ($mode === 'remove-first-window') { self::assertSame(['positions' => [2], 'high' => 2, 'lost' => 1], $two['window']); }
            self::assertSame([], $observer->readAfter('scope', $window->highWater(), 100), 'No later lower-position commit may appear.');
            foreach ($this->relatedTables($connection, $tables) as $table) { self::assertSame($expectedPositions, array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table.' ORDER BY position'))); }
        } finally {
            file_put_contents($directory.'/release', 'yes'); $first->stop(); $second->stop();
        }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend => [$backend, new DbalNoticeTables()];
            yield $backend.'-partial' => [$backend, new DbalNoticeTables(stateTable: 'PartialNoticeState', comparisonTable: 'PartialNoticeComparisons')];
            yield $backend.'-configured' => [$backend, new DbalNoticeTables('NoticeState', 'Order', 'NoticeRelationships', 'NoticeComparisons')];
        }
    }

    public static function routedSelection(): iterable
    {
        yield 'sqlite' => ['sqlite', new DbalNoticeTables()];
    }

    public static function races(): iterable
    {
        // Both lock orders on each driver and the distinct outcomes are retained across this anchor and Integration.
        yield 'sqlite-partial-record-first-remove' => [
            'sqlite', 'record-first-remove', new DbalNoticeTables(stateTable: 'PartialNoticeState', comparisonTable: 'PartialNoticeComparisons')
        ];
    }

    protected function tearDown(): void
    {
        try {
            if ($this->installedTables !== null) {
                $connection = $this->connections[0];
                $tables = $this->installedTables;
                foreach ([$tables->comparisonTable(), $tables->relationshipTable(), $tables->noticeTable(), $tables->stateTable()] as $table) {
                    $connection->executeStatement('DROP TABLE IF EXISTS '.$table->quoted($connection->getDatabasePlatform()));
                }
            }
        } finally {
            foreach ($this->connections as $connection) { $connection->close(); }
            foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-notice-removal-'); }
            parent::tearDown();
        }
    }

    private function assertRemovalRejected(callable $operation): void
    {
        try { $operation(); self::fail('Unknown, stale and denied targets must share rejection.'); }
        catch (NoticeRemovalRejected $failure) { self::assertSame('Notice removal rejected.', $failure->getMessage()); }
    }

    private function database(string $backend, DbalNoticeTables $tables): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/notices.sqlite']; }
        else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual owned disposable '.$backend.' DSN is mandatory; skips do not qualify.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = $this->connect($params);
        $this->reset($connection, $tables);
        $this->installedTables = $tables;
        return $connection;
    }

    private function reset(Connection $connection, DbalNoticeTables $tables): void
    {
        // Drop children from every journey selection before shared default parents.
        $names = ['IsolationComparisons', 'IsolationRelations', 'PartialComparisons', 'PartialRelations'];
        foreach (self::databases() as [, $selection]) {
            foreach ([$selection->comparisonTable(), $selection->relationshipTable()] as $table) { $names[] = $table->name(); }
        }
        $names = [...$names, 'Select', 'IsolationState', 'PartialState'];
        foreach (self::databases() as [, $selection]) {
            foreach ([$selection->noticeTable(), $selection->stateTable()] as $table) { $names[] = $table->name(); }
        }
        foreach (array_unique($names) as $name) { $connection->executeStatement('DROP TABLE IF EXISTS '.$connection->getDatabasePlatform()->quoteSingleIdentifier($name)); }
        (new DbalNoticeSchema($tables))->install($connection);
    }

    private function relatedTables(Connection $connection, DbalNoticeTables $tables): array
    {
        return array_map(static fn ($table) => $table->quoted($connection->getDatabasePlatform()), [$tables->noticeTable(), $tables->relationshipTable(), $tables->comparisonTable()]);
    }

    private function workerTables(DbalNoticeTables $tables): array
    {
        return ['stateTable' => $tables->stateTable()->name(), 'noticeTable' => $tables->noticeTable()->name(), 'relationshipTable' => $tables->relationshipTable()->name(), 'comparisonTable' => $tables->comparisonTable()->name()];
    }

    private function connect(array $params): Connection
    {
        $connection = DriverManager::getConnection($params);
        if ($params['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
        $this->connections[] = $connection; return $connection;
    }

    private function proxy(Connection $connection): Connection
    {
        $proxy = $this->mock(Connection::class);
        foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'isAutoCommit', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'rollBack', 'delete', 'update'] as $method) {
            $proxy->shouldReceive($method)->andReturnUsing(static fn (...$args) => $connection->$method(...$args));
        }
        return $proxy;
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir().'/fight-notice-removal-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($directory, 0700));
        $this->directories[] = $directory; return $directory;
    }

    private function waitFor(string $path, Process $worker): void
    {
        $deadline = microtime(true) + 12;
        while (!file_exists($path)) {
            if (!$worker->isRunning() || microtime(true) > $deadline) { self::fail('Worker barrier not reached: '.$worker->getErrorOutput().$worker->getOutput()); }
            usleep(10000);
        }
        self::assertFileExists($path);
    }
}
