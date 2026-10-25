<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeStore;
use Fight\Common\Application\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Notice\Exception\NoticeRejected;
use Fight\Common\Application\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Application\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\NoticeRemovalSelection;
use Fight\Test\Common\TestCase\Notice\NoticeRemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class NoticeRemovalJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('databases')]
    public function test_that_bounded_commands_preserve_distinct_unselected_scopes_producers_and_relationships(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new NoticeRemovalFixture($connection);
        $fixture->recording->record();
        $fixture->recording->record(['producer' => 'producer-2', 'topic' => 'other-topic']);
        $fixture->recording->record(['scope' => 'scope-2']);
        $fixture->recording->record(['id' => 'broadcast', 'relationships' => ['subject' => null, 'recipients' => [], 'author' => null, 'command' => null, 'event' => null, 'action' => null]]);
        $before = $fixture->recording->store->readWindow('scope', 0, 100);
        $selection = $fixture->selection([NoticeRemovalFixture::target(), NoticeRemovalFixture::target(4, 'broadcast')]);
        $fixture->remove($selection); $this->assertRemovalRejected(fn () => $fixture->remove($selection));
        $fresh = new DbalNoticeStore($this->connect($connection->getParams()));
        $window = $fresh->readWindow('scope', 0, 100);
        self::assertSame([2], array_map(static fn ($row) => $row->position(), $window->notices()));
        self::assertSame($before->notices()[1]->notice()->toArray(), $window->notices()[0]->notice()->toArray());
        self::assertSame($before->notices()[1]->recordedAt(), $window->notices()[0]->recordedAt());
        self::assertSame(3, $fresh->readAfter('scope-2', 0, 100)[0]->position());
        self::assertSame(4, $window->lostThrough()); self::assertSame(4, $window->highWater());
        self::assertFalse($window->hasUnbrokenHistoryAfter($before->namespace(), 0));
        foreach (array_slice(DbalNoticeSchema::TABLES, 1) as $table) { self::assertSame([2, 3], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table.' ORDER BY position'))); }
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

    #[DataProvider('databases')]
    public function test_that_exact_maximum_batch_removes_only_selected_records_and_oversized_input_mutates_nothing(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new NoticeRemovalFixture($connection); $targets = [];
        for ($i = 1; $i <= 100; ++$i) {
            $fixture->recording->record(['id' => 'target-'.$i]); $targets[] = NoticeRemovalFixture::target($i, 'target-'.$i);
        }
        $fixture->recording->record(['id' => 'survivor']);
        $selection = $fixture->selection($targets);
        try {
            NoticeRemovalSelection::fromArray(array_replace($selection->toArray(), ['targets' => [...$targets, NoticeRemovalFixture::target(101, 'survivor')]]));
            self::fail('101 targets must reject before dispatch.');
        } catch (DomainException) {
            self::assertSame(0, $fixture->admissions); self::assertSame(101, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_notices'));
            self::assertSame(0, (int) $connection->fetchOne('SELECT lost_through FROM fight_notice_state'));
        }
        $fixture->remove($selection);
        $window = (new DbalNoticeStore($this->connect($connection->getParams())))->readWindow('scope', 0, 100);
        self::assertSame([101], array_map(static fn ($row) => $row->position(), $window->notices()));
        self::assertSame('survivor', $window->notices()[0]->notice()->id());
        self::assertSame(100, $window->lostThrough()); self::assertSame(101, $window->highWater());
        foreach (array_slice(DbalNoticeSchema::TABLES, 1) as $table) { self::assertSame([101], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table))); }
    }

    #[DataProvider('databases')]
    public function test_that_every_admission_preflights_without_partial_change_or_recording_permission_substitution(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new NoticeRemovalFixture($connection);
        $fixture->recording->record(['relationships' => ['subject' => null, 'recipients' => [], 'author' => null, 'command' => null, 'event' => null, 'action' => null]]);
        $fixture->recording->record(['id' => 'notice-2']);
        $selection = $fixture->selection([NoticeRemovalFixture::target(), NoticeRemovalFixture::target(2, 'notice-2')]);
        foreach (['subject-agent', 'recipient-agent', 'other-recipient', 'author-agent', 'command-id', 'event-id', 'action-id'] as $reference) {
            $fixture->deniedReferences = [$reference];
            try { $fixture->remove($selection); self::fail('Every retained relationship requires removal authority.'); } catch (NoticeRemovalRejected) {
                self::assertSame(0, (int) $connection->fetchOne('SELECT lost_through FROM fight_notice_state'));
                self::assertCount(2, $fixture->recording->store->readAfter('scope', 0, 100));
            }
        }
        $fixture->deniedReferences = [];
        foreach ([NoticeRemovalFixture::target(1, 'notice-1', 'SUPER_ADMIN'), NoticeRemovalFixture::target(1, 'notice-1', 'scope', 'cleanup-caller')] as $denied) {
            try { $fixture->remove($fixture->selection([NoticeRemovalFixture::target(2, 'notice-2'), $denied])); self::fail('Submitted identity is not authority.'); } catch (NoticeRemovalRejected) { self::assertCount(2, $fixture->recording->store->readAfter('scope', 0, 100)); }
        }
        $fixture->recording->allowed = true; $fixture->allowed = false;
        try { $fixture->remove($selection); self::fail('Recording permission does not authorize removal.'); } catch (NoticeRemovalRejected) { self::assertCount(2, $fixture->recording->store->readAfter('scope', 0, 100)); }
        $fixture->allowed = true; $fixture->deniedReferences = ['subject-agent'];
        foreach ([$fixture->selection([NoticeRemovalFixture::target(2, 'notice-2')]), $fixture->selection([NoticeRemovalFixture::target(3, 'missing')])] as $request) {
            $this->assertRemovalRejected(fn () => $fixture->remove($request));
        }
        $fixture->deniedReferences = [];
        $this->assertRemovalRejected(fn () => $fixture->remove($fixture->selection([NoticeRemovalFixture::target(), NoticeRemovalFixture::target(3, 'missing')])));
        self::assertCount(2, $fixture->recording->store->readAfter('scope', 0, 100));
        $fixture->recording->allowed = false; $fixture->remove($selection);
        self::assertSame([], $fixture->recording->store->readAfter('scope', 0, 100));
    }

    #[DataProvider('databases')]
    public function test_that_repeated_empty_feed_reconstruction_keeps_only_one_bounded_body_free_loss_record(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new NoticeRemovalFixture($connection);
        $namespace = $fixture->recording->store->readWindow('scope', 0, 1)->namespace();
        for ($i = 1; $i <= 128; ++$i) {
            $fixture->recording->record(['scope' => $i % 2 === 0 ? 'scope-2' : 'scope']);
            $selection = NoticeRemovalSelection::fromArray(['version' => 1, 'namespace' => $namespace, 'targets' => [NoticeRemovalFixture::target($i, 'notice-1', $i % 2 === 0 ? 'scope-2' : 'scope')]]);
            $fixture->remove($selection);
        }
        $window = (new DbalNoticeStore($this->connect($connection->getParams())))->readWindow('scope', 0, 100);
        self::assertSame([], $window->notices()); self::assertSame(128, $window->highWater()); self::assertSame(128, $window->lostThrough());
        self::assertFalse($window->hasUnbrokenHistoryAfter($namespace, 0));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_notice_state'));
        foreach (array_slice(DbalNoticeSchema::TABLES, 1) as $table) { self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$table)); }
        self::assertSame(['singleton', 'version', 'last_position', 'namespace', 'lost_through'], array_keys($connection->fetchAssociative('SELECT * FROM fight_notice_state')));
        $fixture->recording->record(); self::assertSame(129, $fixture->recording->store->readAfter('scope', 128, 100)[0]->position());
        // Operational loss/retirement cannot reuse an old continuation or stale removal across namespaces.
        $newNamespace = bin2hex(random_bytes(16));
        $connection->update('fight_notice_state', ['namespace' => $newNamespace, 'lost_through' => 129], ['singleton' => 1]);
        $retired = $fixture->recording->store->readWindow('scope', 128, 100);
        self::assertFalse($retired->hasUnbrokenHistoryAfter($namespace, 129));
        try { $fixture->remove($selection); self::fail('Retired namespace must reject.'); } catch (NoticeRemovalRejected) { self::assertCount(1, $fixture->recording->store->readAfter('scope', 0, 100)); }
        $connection->delete('fight_notice_state', ['singleton' => 1]);
        try { $fixture->recording->store->readWindow('scope', 0, 100); self::fail('Unavailable evidence cannot become complete empty history.'); } catch (RuntimeException) { self::assertTrue(true); }
    }

    #[DataProvider('databases')]
    public function test_that_all_delete_loss_and_commit_faults_are_atomic_and_lost_acknowledgment_is_explicit(string $backend): void
    {
        $connection = $this->database($backend);
        foreach (['delete-1', 'delete-2', 'loss', 'commit-before', 'commit-after'] as $stage) {
            $seed = new NoticeRemovalFixture($connection); $seed->recording->record(); $seed->recording->record(['id' => 'notice-2']); $seed->recording->record(['id' => 'survivor']);
            $selection = $seed->selection([NoticeRemovalFixture::target(), NoticeRemovalFixture::target(2, 'notice-2')]);
            $proxy = $this->proxy($connection); $deletes = 0;
            $failure = new class ('Injected private diagnostic.') extends RuntimeException implements Exception {};
            $proxy->shouldReceive('delete')->andReturnUsing(static function (...$args) use ($connection, $stage, $failure, &$deletes) {
                $result = $connection->delete(...$args); ++$deletes;
                if ($stage === 'delete-'.$deletes) { throw $failure; } return $result;
            });
            $proxy->shouldReceive('update')->andReturnUsing(static function (...$args) use ($connection, $stage, $failure) {
                $result = $connection->update(...$args); if ($stage === 'loss') { throw $failure; } return $result;
            });
            $proxy->shouldReceive('commit')->andReturnUsing(static function () use ($connection, $stage, $failure): void {
                if ($stage === 'commit-after') { $connection->commit(); } throw $failure;
            });
            $fixture = new NoticeRemovalFixture($proxy);
            try { $fixture->remove($selection); self::fail('Fault must not acknowledge success.'); } catch (NoticeStorageFailed $caught) {
                self::assertSame(in_array($stage, ['commit-before', 'commit-after'], true), $caught->outcomeUncertain());
                self::assertSame('Notice storage failed.', $caught->getMessage());
            }
            $fresh = new DbalNoticeStore($this->connect($connection->getParams())); $window = $fresh->readWindow('scope', 0, 100);
            $committed = $stage === 'commit-after';
            self::assertSame($committed ? [3] : [1, 2, 3], array_map(static fn ($row) => $row->position(), $window->notices()));
            self::assertSame($committed ? 2 : 0, $window->lostThrough()); self::assertSame(3, $window->highWater());
            foreach (array_slice(DbalNoticeSchema::TABLES, 1) as $table) { self::assertSame($committed ? [3] : [1, 2, 3], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table.' ORDER BY position'))); }
            // A lost successful reply repeats as the same safe missing/denied failure, never a receipt.
            if ($committed) { $this->assertRemovalRejected(fn () => $seed->remove($selection)); }
            else { $seed->remove($selection); }
            self::assertSame([3], array_map(static fn ($row) => $row->position(), $fresh->readAfter('scope', 0, 100)));
            $this->reset($connection);
        }
    }

    #[DataProvider('databases')]
    public function test_that_real_database_loss_constraint_failure_rolls_back_cascades_and_remains_reusable(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new NoticeRemovalFixture($connection);
        $fixture->recording->record(); $fixture->recording->record(['id' => 'survivor']);
        $selection = $fixture->selection([NoticeRemovalFixture::target()]);
        if ($backend === 'sqlite') { $connection->executeStatement("CREATE TRIGGER notice_loss_failure BEFORE UPDATE OF lost_through ON fight_notice_state BEGIN SELECT RAISE(ABORT, 'private'); END"); }
        else { $connection->executeStatement('ALTER TABLE fight_notice_state ADD CONSTRAINT notice_loss_failure CHECK (lost_through = 0)'); }
        try { $fixture->remove($selection); self::fail('Actual database failure must roll back deletion and loss.'); }
        catch (NoticeStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); self::assertSame('Notice storage failed.', $failure->getMessage()); }
        self::assertFalse($fixture->recording->store->isClosed());
        $window = (new DbalNoticeStore($this->connect($connection->getParams())))->readWindow('scope', 0, 100);
        self::assertSame([1, 2], array_map(static fn ($row) => $row->position(), $window->notices())); self::assertSame(0, $window->lostThrough());
        foreach (array_slice(DbalNoticeSchema::TABLES, 1) as $table) { self::assertSame([1, 2], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table.' ORDER BY position'))); }
        if ($backend === 'sqlite') { $connection->executeStatement('DROP TRIGGER notice_loss_failure'); }
        elseif ($backend === 'mysql') { $connection->executeStatement('ALTER TABLE fight_notice_state DROP CHECK notice_loss_failure'); }
        else { $connection->executeStatement('ALTER TABLE fight_notice_state DROP CONSTRAINT notice_loss_failure'); }
        $fixture->remove($selection); self::assertSame([2], array_map(static fn ($row) => $row->position(), $fixture->recording->store->readAfter('scope', 0, 100)));
    }

    #[DataProvider('databases')]
    public function test_that_enclosing_work_is_untouched_and_later_consumer_failure_cannot_undo_removal(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new NoticeRemovalFixture($connection); $fixture->recording->record();
        $selection = $fixture->selection([NoticeRemovalFixture::target()]);
        $connection->executeStatement('CREATE TABLE notice_removal_caller (id INTEGER)');
        $connection->beginTransaction(); $connection->insert('notice_removal_caller', ['id' => 42]);
        try { $fixture->remove($selection); self::fail('No savepoint acknowledgment.'); } catch (LogicException) {
            self::assertTrue($connection->isTransactionActive()); self::assertSame(1, $connection->getTransactionNestingLevel());
            self::assertSame(0, $fixture->admissions); self::assertSame(42, (int) $connection->fetchOne('SELECT id FROM notice_removal_caller'));
        }
        $connection->commit();
        self::assertSame(42, (int) $this->connect($connection->getParams())->fetchOne('SELECT id FROM notice_removal_caller'));
        $connection->executeStatement('DROP TABLE notice_removal_caller');
        try { $fixture->remove($selection); self::assertFalse($connection->isTransactionActive()); throw new RuntimeException('later consumer publisher failed'); } catch (RuntimeException $failure) { self::assertSame('later consumer publisher failed', $failure->getMessage()); }
        $window = (new DbalNoticeStore($this->connect($connection->getParams())))->readWindow('scope', 0, 100);
        self::assertSame([], $window->notices()); self::assertSame(1, $window->lostThrough());
    }

    #[DataProvider('races')]
    public function test_that_real_recording_removal_and_retry_races_serialize_committed_survivors_and_coherent_loss(string $backend, string $mode): void
    {
        $connection = $this->database($backend); $fixture = new NoticeRemovalFixture($connection);
        $fixture->recording->record(); $fixture->recording->record(['id' => 'survivor']);
        $selection = $fixture->selection([NoticeRemovalFixture::target()]); $directory = $this->directory();
        $config = $directory.'/config.json'; file_put_contents($config, json_encode(['params' => $connection->getParams(), 'mode' => $mode, 'selection' => $selection->toArray()], JSON_THROW_ON_ERROR));
        $first = new Process([PHP_BINARY, __DIR__.'/../TestCase/Notice/removal-worker.php', $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../TestCase/Notice/removal-worker.php', $config, 'second'], timeout: 25);
        try {
            $first->start(); $this->waitFor($directory.'/first-locked', $first);
            $second->start(); $this->waitFor($directory.'/second-started', $second); usleep(200000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted', 'Waiting work must authorize only after acquiring the lock.');
            $observer = new DbalNoticeStore($this->connect($connection->getParams()));
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
            foreach (array_slice(DbalNoticeSchema::TABLES, 1) as $table) { self::assertSame($expectedPositions, array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table.' ORDER BY position'))); }
        } finally {
            file_put_contents($directory.'/release', 'yes'); $first->stop(); $second->stop();
        }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }

    public static function races(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            foreach (['remove-first-identical', 'remove-first-conflicting', 'remove-first-distinct', 'remove-first-repeat', 'record-first-remove', 'retry-first-remove', 'conflict-first-remove', 'remove-rollback-retry', 'remove-first-revoked-record', 'record-first-revoked-remove', 'remove-first-window'] as $mode) { yield $backend.'-'.$mode => [$backend, $mode]; }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }
        foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-notice-removal-'); }
        parent::tearDown();
    }

    private function assertRemovalRejected(callable $operation): void
    {
        try { $operation(); self::fail('Unknown, stale and denied targets must share rejection.'); }
        catch (NoticeRemovalRejected $failure) { self::assertSame('Notice removal rejected.', $failure->getMessage()); }
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/notices.sqlite']; }
        else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual owned disposable '.$backend.' DSN is mandatory; skips do not qualify.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = $this->connect($params); $this->reset($connection); return $connection;
    }

    private function reset(Connection $connection): void
    {
        foreach (array_reverse(DbalNoticeSchema::TABLES) as $table) { $connection->executeStatement('DROP TABLE IF EXISTS '.$table); }
        (new DbalNoticeSchema())->install($connection);
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
        foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'rollBack'] as $method) {
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
