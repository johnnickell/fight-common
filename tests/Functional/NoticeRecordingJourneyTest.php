<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeStore;
use Fight\Common\Application\Notice\Exception\CommittedNoticeDispatchFailed;
use Fight\Common\Application\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Notice\Exception\NoticeRejected;
use Fight\Common\Application\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Notice\Command\RecordNotice;
use Fight\Common\Domain\Notice\Event\NoticeRecorded;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\RecordedNotice;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class NoticeRecordingJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('databases')]
    public function test_that_commands_survive_reconstruction_with_independent_namespaces_and_unknowns(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new NoticeFixture($connection);
        $fixture->record();
        $first = $fixture->store->readAfter('scope', 0, 1)[0];
        $fixture->record(['producer' => 'producer-2']);
        $fixture->record(['scope' => 'scope-2']);
        $unknown = ['subject' => null, 'recipients' => null, 'author' => null, 'command' => null, 'event' => null, 'action' => null];
        $fixture->record(['id' => 'unknown', 'relationships' => $unknown, 'occurred_at' => null]);
        $broadcast = $unknown; $broadcast['recipients'] = [];
        $fixture->record(['id' => 'broadcast', 'relationships' => $broadcast, 'occurred_at' => '0001-01-01T00:00:00Z']);
        $fixture->record(['id' => 'NOTICE-1', 'occurred_at' => '9999-12-31T23:59:59.999999Z']);
        $params = $connection->getParams(); $connection->close();
        $fresh = new NoticeFixture($this->connect($params));
        $fresh->events->addHandler(ClassName::underscore(NoticeRecorded::class), static function (): void { self::fail('A retry must not replay publication.'); });
        $fresh->record();
        $rows = $fresh->store->readAfter('scope', 0, 100);
        self::assertSame([1, 2, 4, 5, 6], array_map(static fn ($row) => $row->position(), $rows));
        self::assertSame($first->recordedAt(), $rows[0]->recordedAt());
        self::assertSame(Notice::fromArray(NoticeFixture::data())->toArray(), $rows[0]->notice()->toArray());
        self::assertSame($unknown, $rows[2]->notice()->relationships()->toArray()); self::assertNull($rows[2]->notice()->occurrence());
        self::assertSame($broadcast, $rows[3]->notice()->relationships()->toArray());
        self::assertSame('0001-01-01T00:00:00Z', $rows[3]->notice()->occurrence()->toString());
        self::assertSame('9999-12-31T23:59:59.999999Z', $rows[4]->notice()->occurrence()->toString());
        self::assertSame('scope-2', $fresh->store->readAfter('scope-2', 0, 1)[0]->notice()->scope());
        self::assertSame([4, 5], array_map(static fn ($row) => $row->position(), $fresh->store->readAfter('scope', 2, 2)));
        self::assertSame([], $fresh->store->readAfter('scope', 6, 100));
        self::assertSame([], $fresh->store->readAfter('missing-scope', 0, 100));
    }

    #[DataProvider('databases')]
    public function test_that_current_trust_and_reference_permissions_precede_original_or_retry_identity_lookup(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new NoticeFixture($connection);
        $fixture->record();
        foreach (['producer', 'scope', 'subject', 'recipient', 'author', 'command', 'event', 'action'] as $denial) {
            $change = ['id' => 'new']; $fixture->references = ['subject-agent', 'recipient-agent', 'other-recipient', 'author-agent', 'command-id', 'event-id', 'action-id'];
            if ($denial === 'producer' || $denial === 'scope') { $change[$denial] = 'SUPER_ADMIN'; }
            else {
                $reference = $denial === 'recipient' ? 'recipient-agent' : ($denial === 'subject' || $denial === 'author' ? $denial.'-agent' : $denial.'-id');
                $fixture->references = array_values(array_diff($fixture->references, [$reference]));
            }
            foreach (['new', 'notice-1'] as $id) {
                $change['id'] = $id;
                try { $fixture->record($change); self::fail('Expected current independent admission.'); } catch (NoticeRejected) { self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_notices')); }
            }
        }
        $fixture->allowed = false;
        try { $fixture->record(['topic' => 'conflicting']); self::fail('Denial must precede conflict.'); } catch (NoticeRejected) { self::assertSame(1, (int) $connection->fetchOne('SELECT last_position FROM fight_notice_state')); }
        $fixture->allowed = true; $fixture->references = ['subject-agent', 'recipient-agent', 'other-recipient', 'author-agent', 'command-id', 'event-id', 'action-id'];
        foreach (['topic', 'type', 'occurred_at', 'metadata', 'relationships'] as $field) {
            $change = match ($field) { 'topic', 'type' => [$field => 'changed'], 'occurred_at' => [$field => '2026-10-25T01:02:03.120Z'], 'metadata' => [$field => ['app/revision' => 4]], 'relationships' => [$field => array_replace(NoticeFixture::data()['relationships'], ['author' => null])] };
            try { $fixture->record($change); self::fail('Changed accepted content must conflict.'); } catch (NoticeConflict) { self::assertSame(1, (int) $connection->fetchOne('SELECT last_position FROM fight_notice_state')); }
        }
        $data = NoticeFixture::data(['metadata' => ['app/body' => ['protected']]]);
        try { $fixture->bus->execute(RecordNotice::fromArray($data)); self::fail('Protected shape must reject before dispatch.'); } catch (\Fight\Common\Domain\Exception\DomainException) { self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_notices')); }
    }

    #[DataProvider('databases')]
    public function test_that_every_related_write_failure_and_precommit_failure_rolls_back_real_storage(string $backend): void
    {
        $connection = $this->database($backend);
        foreach (['fight_notices', 'fight_notice_relationships', 'fight_notice_comparisons', 'commit'] as $stage) {
            $proxy = $this->proxy($connection);
            $failure = new class ('Injected private storage diagnostic.') extends RuntimeException implements Exception {};
            $proxy->shouldReceive('insert')->andReturnUsing(static function (...$arguments) use ($connection, $stage, $failure) {
                $result = $connection->insert(...$arguments);
                if ($arguments[0] === $stage) { throw $failure; }
                return $result;
            });
            if ($stage === 'commit') { $proxy->shouldReceive('commit')->once()->andThrow($failure); }
            else { $proxy->shouldReceive('commit')->never(); }
            $fixture = new NoticeFixture($proxy); $events = 0;
            $fixture->events->addHandler(ClassName::underscore(NoticeRecorded::class), static function () use (&$events): void { ++$events; });
            try { $fixture->record(); self::fail('Injected storage boundary must fail.'); } catch (NoticeStorageFailed $caught) { self::assertSame($stage === 'commit', $caught->outcomeUncertain()); self::assertSame('Notice storage failed.', $caught->getMessage()); }
            self::assertSame(0, $events); self::assertFalse($connection->isTransactionActive());
            $other = $this->connect($connection->getParams());
            foreach (array_slice(DbalNoticeSchema::TABLES, 1) as $table) { self::assertSame(0, (int) $other->fetchOne('SELECT COUNT(*) FROM '.$table)); }
            self::assertSame(0, (int) $other->fetchOne('SELECT last_position FROM fight_notice_state'));
        }
        $fixture = new NoticeFixture($connection); $fixture->record(); self::assertSame(1, $fixture->store->readAfter('scope', 0, 1)[0]->position());
    }

    #[DataProvider('databases')]
    public function test_that_database_constraint_failure_is_atomic_and_reusable(string $backend): void
    {
        $connection = $this->database($backend);
        if ($backend === 'sqlite') { $connection->executeStatement("CREATE TRIGGER notice_fail BEFORE INSERT ON fight_notice_comparisons BEGIN SELECT RAISE(ABORT, 'private'); END"); }
        else { $connection->executeStatement('ALTER TABLE fight_notice_comparisons ADD CONSTRAINT notice_fail CHECK (position < 0)'); }
        $fixture = new NoticeFixture($connection);
        try { $fixture->record(); self::fail('Expected actual database constraint failure.'); } catch (NoticeStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); }
        self::assertSame([], $fixture->store->readAfter('scope', 0, 100));
        self::assertSame(0, (int) $connection->fetchOne('SELECT last_position FROM fight_notice_state'));
        if ($backend === 'sqlite') { $connection->executeStatement('DROP TRIGGER notice_fail'); }
        elseif ($backend === 'mysql') { $connection->executeStatement('ALTER TABLE fight_notice_comparisons DROP CHECK notice_fail'); }
        else { $connection->executeStatement('ALTER TABLE fight_notice_comparisons DROP CONSTRAINT notice_fail'); }
        $fixture->record(); self::assertSame(1, $fixture->store->readAfter('scope', 0, 100)[0]->position());
    }

    #[DataProvider('databases')]
    public function test_that_postcommit_subscriber_failure_and_lost_commit_acknowledgment_preserve_reconstructable_data(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new NoticeFixture($connection); $other = $this->connect($connection->getParams()); $calls = 0;
        $fixture->events->addHandler(ClassName::underscore(NoticeRecorded::class), static function (EventMessage $event) use ($other, $connection, &$calls): void {
            ++$calls; self::assertFalse($connection->isTransactionActive());
            self::assertSame($event->payload()->toArray()['position'], (new DbalNoticeStore($other))->readAfter('scope', 0, 100)[0]->position());
            throw new RuntimeException('private delivery failure');
        });
        try { $fixture->record(); self::fail('Expected committed dispatch failure.'); } catch (CommittedNoticeDispatchFailed $failure) { self::assertSame(1, $failure->event()->toArray()['position']); }
        $fixture->record(); self::assertSame(1, $calls);
        $proxy = $this->proxy($connection);
        $proxy->shouldReceive('insert')->andReturnUsing(static fn (...$args) => $connection->insert(...$args));
        $proxy->shouldReceive('commit')->once()->andReturnUsing(static function () use ($connection): void { $connection->commit(); throw new RuntimeException('lost actual commit acknowledgment'); });
        $uncertain = new NoticeFixture($proxy);
        $uncertain->events->addHandler(ClassName::underscore(NoticeRecorded::class), static function (): void { self::fail('Uncertain acknowledgment must not dispatch.'); });
        try { $uncertain->record(['id' => 'notice-2']); self::fail('Expected uncertainty.'); } catch (NoticeStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); }
        self::assertTrue($uncertain->store->isClosed());
        $reconstructed = new NoticeFixture($this->connect($connection->getParams()));
        $rows = $reconstructed->store->readAfter('scope', 0, 100); self::assertSame([1, 2], array_map(static fn ($row) => $row->position(), $rows));
        $reconstructed->events->addHandler(ClassName::underscore(NoticeRecorded::class), static function (): void { self::fail('Reconstruction retry must not replay facts.'); });
        $reconstructed->record(['id' => 'notice-2']);
        self::assertSame($rows[1]->recordedAt(), $reconstructed->store->readAfter('scope', 1, 100)[0]->recordedAt());
    }

    #[DataProvider('databases')]
    public function test_that_enclosing_transaction_rejection_preserves_real_caller_work_and_capacity_is_portable(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new NoticeFixture($connection);
        $connection->executeStatement('CREATE TABLE notice_caller_work (id INTEGER)');
        $connection->beginTransaction(); $connection->insert('notice_caller_work', ['id' => 42]);
        try { $fixture->record(); self::fail('Outer transaction unsupported.'); } catch (\LogicException) {
            self::assertTrue($connection->isTransactionActive()); self::assertSame(0, $fixture->admissions);
            self::assertSame(42, (int) $connection->fetchOne('SELECT id FROM notice_caller_work')); self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        }
        $connection->commit(); self::assertSame(42, (int) $this->connect($connection->getParams())->fetchOne('SELECT id FROM notice_caller_work'));
        $connection->executeStatement('DROP TABLE notice_caller_work');
        $connection->update('fight_notice_state', ['last_position' => RecordedNotice::MAX_POSITION - 1], ['singleton' => 1]);
        $fixture->record(); self::assertSame(RecordedNotice::MAX_POSITION, $fixture->store->readAfter('scope', 0, 1)[0]->position());
        try { $fixture->record(['id' => 'overflow']); self::fail('Overflow must reject without database coercion.'); } catch (\Fight\Common\Domain\Exception\DomainException) { self::assertSame(RecordedNotice::MAX_POSITION, (int) $connection->fetchOne('SELECT last_position FROM fight_notice_state')); }
        $fixture->record(); self::assertCount(1, $fixture->store->readAfter('scope', 0, 100));
    }

    #[DataProvider('races')]
    public function test_that_independent_delayed_writers_hold_allocation_through_commit_and_reauthorize_after_wait(string $backend, string $mode): void
    {
        $connection = $this->database($backend); $directory = $this->directory();
        $config = $directory.'/config.json'; file_put_contents($config, json_encode(['params' => $connection->getParams(), 'mode' => $mode], JSON_THROW_ON_ERROR));
        $first = new Process([PHP_BINARY, __DIR__.'/../TestCase/Notice/record-worker.php', $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../TestCase/Notice/record-worker.php', $config, 'second'], timeout: 25);
        try {
            $first->start(); $this->waitFor($directory.'/first-precommit', $first);
            self::assertSame('1', file_get_contents($directory.'/first-precommit'));
            $second->start(); $this->waitFor($directory.'/second-started', $second); usleep(200000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted');
            $reader = new DbalNoticeStore($this->connect($connection->getParams()));
            self::assertSame([], $reader->readAfter('scope', 0, 100), 'No uncommitted allocation may escape.');
            if ($mode === 'revoked') { file_put_contents($directory.'/revoked', 'yes'); }
            file_put_contents($directory.'/release', 'yes');
            self::assertSame(0, $first->wait(), $first->getErrorOutput()); self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $one = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR); $two = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($mode === 'rollback' ? NoticeStorageFailed::class : 'recorded', $one['status']);
            $expected = match ($mode) { 'conflict' => NoticeConflict::class, 'revoked' => NoticeRejected::class, default => 'recorded' };
            self::assertSame($expected, $two['status']); self::assertSame(1, $two['admissions']);
            self::assertSame($mode === 'rollback' ? 0 : 1, $one['events']); self::assertSame(in_array($mode, ['distinct', 'rollback'], true) ? 1 : 0, $two['events']);
            $fresh = new DbalNoticeStore($this->connect($connection->getParams()));
            $rows = $fresh->readAfter('scope', 0, 100);
            self::assertSame($mode === 'distinct' ? [1, 2] : [1], array_map(static fn ($row) => $row->position(), $rows));
            self::assertSame($mode === 'rollback' ? 'notice-2' : 'notice-1', $rows[0]->notice()->id());
            self::assertSame('catalog/tools', $rows[0]->notice()->toArray()['topic']);
            $later = end($rows)->position();
            self::assertSame([], $fresh->readAfter('scope', $later, 100), 'No delayed lower-position commit remains.');
            if ($mode === 'rollback') {
                (new NoticeFixture($connection))->record();
                self::assertSame(2, $fresh->readAfter('scope', $later, 100)[0]->position(), 'Retry allocates after the already observed committed position.');
            }
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
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { foreach (['duplicate', 'conflict', 'distinct', 'rollback', 'revoked'] as $mode) { yield $backend.'-'.$mode => [$backend, $mode]; } }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }
        foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-notice-'); }
        parent::tearDown();
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/notices.sqlite']; }
        else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual owned disposable '.$backend.' DSN is mandatory; skips do not qualify.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = $this->connect($params);
        foreach (array_reverse(DbalNoticeSchema::TABLES) as $table) { $connection->executeStatement('DROP TABLE IF EXISTS '.$table); }
        (new DbalNoticeSchema())->install($connection); return $connection;
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
        foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'update', 'rollBack'] as $method) {
            $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $connection->$method(...$arguments));
        }
        return $proxy;
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir().'/fight-notice-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($directory, 0700));
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
