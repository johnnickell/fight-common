<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeStore;
use Fight\Common\Application\Notice\CommandHandler\RecordNoticeHandler;
use Fight\Common\Application\Notice\Exception\CommittedNoticeDispatchFailed;
use Fight\Common\Application\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Notice\Exception\NoticeRejected;
use Fight\Common\Application\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Application\Notice\Service\NoticeRecorder;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Notice\Event\NoticeRecorded;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\RecordedNotice;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(DbalNoticeSchema::class)]
#[CoversClass(DbalNoticeStore::class)]
#[CoversClass(NoticeRecorder::class)]
#[CoversClass(RecordNoticeHandler::class)]
#[CoversClass(NoticeRejected::class)]
#[CoversClass(NoticeConflict::class)]
#[CoversClass(NoticeStorageFailed::class)]
#[CoversClass(CommittedNoticeDispatchFailed::class)]
final class DbalNoticeStoreTest extends UnitTestCase
{
    public function test_that_routed_recording_commits_related_evidence_and_deduplicates_immutable_receipts(): void
    {
        $connection = $this->database(); $fixture = new NoticeFixture($connection);
        self::assertSame($connection, $fixture->store->connection());
        self::assertFalse($fixture->store->isClosed());
        self::assertSame(\Fight\Common\Domain\Notice\Command\RecordNotice::class, RecordNoticeHandler::commandRegistration());
        $events = [];
        $fixture->events->addHandler(ClassName::underscore(NoticeRecorded::class), static function (EventMessage $event) use (&$events, $connection): void {
            self::assertFalse($connection->isTransactionActive()); $events[] = $event->payload()->toArray();
        });
        $fixture->record();
        $first = $fixture->store->readAfter('scope', 0, 1)[0];
        $fixture->record();
        self::assertSame(2, $fixture->admissions); self::assertCount(1, $events);
        $retained = $fixture->store->commitTransactional(fn () => $fixture->store->getByIdentity('scope', 'producer', 'notice-1'));
        self::assertSame(1, $retained->position()); self::assertSame($first->recordedAt(), $retained->recordedAt());
        self::assertSame(Notice::fromArray(NoticeFixture::data())->comparison(), $retained->notice()->comparison());
        self::assertNull($fixture->store->commitTransactional(fn () => $fixture->store->getByIdentity('scope', 'producer', 'missing')));
        self::assertSame([], $fixture->store->readAfter('scope', 1, 100));
        foreach (['fight_notices', 'fight_notice_relationships', 'fight_notice_comparisons'] as $table) { self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$table)); }
        try { $fixture->record(['topic' => 'different']); self::fail('Expected conflict.'); } catch (NoticeConflict $failure) { self::assertSame('Notice content conflicts.', $failure->getMessage()); }
        $fixture->allowed = false;
        try { $fixture->record(['topic' => 'different']); self::fail('Expected current admission before conflict.'); } catch (NoticeRejected $failure) { self::assertSame('Notice recording rejected.', $failure->getMessage()); }
        self::assertSame($first->notice()->comparison(), $fixture->store->readAfter('scope', 0, 1)[0]->notice()->comparison());
    }

    public function test_that_postcommit_dispatch_failure_keeps_safe_fact_and_retained_recovery_without_replay(): void
    {
        $connection = $this->database(); $fixture = new NoticeFixture($connection); $count = 0;
        $fixture->events->addHandler(ClassName::underscore(NoticeRecorded::class), static function () use (&$count): void { ++$count; throw new RuntimeException('private downstream diagnostic'); });
        try { $fixture->record(); self::fail('Expected postcommit failure.'); } catch (CommittedNoticeDispatchFailed $failure) {
            self::assertSame('Notice committed; fact dispatch failed.', $failure->getMessage());
            self::assertSame(1, $failure->event()->toArray()['position']);
            self::assertArrayNotHasKey('metadata', $failure->event()->toArray());
        }
        self::assertCount(1, (new DbalNoticeStore($connection))->readAfter('scope', 0, 100));
        $fixture->record(); self::assertSame(1, $count);
    }

    public function test_that_foreign_transactions_and_unowned_access_never_commit_or_rollback_caller_work(): void
    {
        $connection = $this->database(); $fixture = new NoticeFixture($connection);
        $connection->executeStatement('CREATE TABLE caller_work (id INTEGER)');
        foreach ([false, true] as $outer) {
            if ($outer) { $connection->beginTransaction(); $connection->insert('caller_work', ['id' => 7]); }
            foreach ([fn () => $fixture->store->commitTransactional(fn () => $fixture->store->readAfter('scope', 0, 1)), fn () => $fixture->store->append(Notice::fromArray(NoticeFixture::data()), '2026-10-25T00:00:00.000000Z'), fn () => $fixture->store->getByIdentity('scope', 'producer', 'notice-1'), fn () => $fixture->store->commitTransactional(fn () => $fixture->store->commitTransactional(static fn () => null))] as $operation) {
                try { $operation(); self::fail('Expected ownership rejection.'); } catch (LogicException) { self::assertSame($outer, $connection->isTransactionActive()); }
            }
            if ($outer) {
                try { $fixture->record(); self::fail('Expected enclosing transaction rejection.'); } catch (LogicException) { self::assertSame(0, $fixture->admissions); }
                self::assertSame(7, (int) $connection->fetchOne('SELECT id FROM caller_work')); $connection->rollBack();
            }
        }
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_notices'));
    }

    public function test_that_related_database_failure_rolls_back_and_does_not_close_a_known_rollback(): void
    {
        $connection = $this->database(); $fixture = new NoticeFixture($connection);
        $connection->executeStatement("CREATE TRIGGER fail_comparison BEFORE INSERT ON fight_notice_comparisons BEGIN SELECT RAISE(ABORT, 'private'); END");
        try { $fixture->record(); self::fail('Expected storage failure.'); } catch (NoticeStorageFailed $failure) {
            self::assertFalse($failure->outcomeUncertain()); self::assertInstanceOf(Exception::class, $failure->getPrevious()); self::assertSame('Notice storage failed.', $failure->getMessage());
        }
        self::assertFalse($fixture->store->isClosed());
        foreach (DbalNoticeSchema::TABLES as $table) { if ($table !== 'fight_notice_state') { self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$table)); } }
        self::assertSame(0, (int) $connection->fetchOne('SELECT last_position FROM fight_notice_state'));
        $connection->executeStatement('DROP TRIGGER fail_comparison'); $fixture->record(); self::assertSame(1, $fixture->store->readAfter('scope', 0, 1)[0]->position());
    }

    #[DataProvider('uncertainties')]
    public function test_that_uncertain_begin_commit_or_rollback_closes_the_boundary(string $stage, bool $active): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new PostgreSQLPlatform());
        $connection->shouldReceive('getTransactionIsolation')->andReturn(TransactionIsolationLevel::READ_COMMITTED);
        $connection->shouldReceive('isTransactionActive')->andReturn(false, false, $active);
        $connection->shouldReceive('getTransactionNestingLevel')->andReturn(1);
        $failure = new RuntimeException('private connection failure');
        if ($stage === 'begin') { $connection->shouldReceive('beginTransaction')->once()->andThrow($failure); }
        else {
            $connection->shouldReceive('beginTransaction')->once();
            $connection->shouldReceive('executeStatement')->once(); $connection->shouldReceive('fetchOne')->once()->andReturn(1);
            if ($stage === 'rollback') { $connection->shouldReceive('rollBack')->once()->andThrow(new RuntimeException('cleanup failure')); }
            else {
                $connection->shouldReceive('commit')->once()->andThrow($failure);
                if ($active) { $connection->shouldReceive('rollBack')->once(); }
            }
        }
        $store = new DbalNoticeStore($connection);
        try { $store->commitTransactional(static function () use ($stage, $failure) { if ($stage === 'rollback') { throw $failure; } return null; }); self::fail('Expected uncertainty.'); }
        catch (NoticeStorageFailed $caught) { self::assertTrue($caught->outcomeUncertain()); self::assertSame($failure, $caught->getPrevious()); }
        self::assertTrue($store->isClosed()); $this->expectException(LogicException::class); $store->commitTransactional(static fn () => null);
    }

    public static function uncertainties(): iterable
    {
        yield ['begin', false]; yield ['commit', false]; yield ['commit', true]; yield ['rollback', false];
    }

    public function test_that_unsupported_platform_active_composition_and_dirty_reads_reject(): void
    {
        $unsupported = $this->mock(Connection::class); $unsupported->shouldReceive('getDatabasePlatform')->andReturn(new OraclePlatform());
        try { new DbalNoticeStore($unsupported); self::fail('Expected platform rejection.'); } catch (InvalidArgumentException) { self::assertTrue(true); }
        $connection = $this->database(); $connection->beginTransaction();
        try { new DbalNoticeStore($connection); self::fail('Expected active composition rejection.'); } catch (LogicException) { self::assertTrue($connection->isTransactionActive()); }
        $connection->rollBack();
        $store = new DbalNoticeStore($connection); $connection->setTransactionIsolation(TransactionIsolationLevel::READ_UNCOMMITTED);
        foreach ([fn () => $store->readAfter('scope', 0, 1), fn () => $store->commitTransactional(static fn () => null)] as $read) {
            try { $read(); self::fail('Expected dirty-read rejection.'); } catch (LogicException) { self::assertFalse($connection->isTransactionActive()); }
        }
        foreach ([new MySQLPlatform(), new PostgreSQLPlatform()] as $platform) {
            $proxy = $this->mock(Connection::class); $proxy->shouldReceive('getDatabasePlatform')->andReturn($platform); $proxy->shouldReceive('isTransactionActive')->andReturn(false); $proxy->shouldReceive('getTransactionIsolation')->andReturn(TransactionIsolationLevel::READ_COMMITTED);
            self::assertFalse((new DbalNoticeStore($proxy))->isClosed());
        }
    }

    #[DataProvider('readBounds')]
    public function test_that_reconstruction_bounds_reject_before_reading(int $position, int $limit): void
    {
        $store = new DbalNoticeStore($this->database()); $this->expectException(DomainException::class); $store->readAfter('scope', $position, $limit);
    }

    public static function readBounds(): iterable
    {
        yield [-1, 1]; yield [2147483648, 1]; yield [0, 0]; yield [0, 101];
    }

    public function test_that_capacity_and_schema_version_reject_without_partial_recording(): void
    {
        $connection = $this->database(); $fixture = new NoticeFixture($connection);
        foreach ([-1, RecordedNotice::MAX_POSITION] as $last) {
            $connection->update('fight_notice_state', ['last_position' => $last], ['singleton' => 1]);
            try { $fixture->record(); self::fail('Expected capacity rejection.'); } catch (DomainException) { self::assertSame($last, (int) $connection->fetchOne('SELECT last_position FROM fight_notice_state')); }
        }
        $connection->update('fight_notice_state', ['last_position' => RecordedNotice::MAX_POSITION - 1], ['singleton' => 1]);
        $fixture->record(); self::assertSame(RecordedNotice::MAX_POSITION, $fixture->store->readAfter('scope', 0, 1)[0]->position());
        $connection->update('fight_notice_state', ['version' => 2], ['singleton' => 1]);
        $this->expectException(RuntimeException::class); $fixture->record();
    }

    #[DataProvider('corruption')]
    public function test_that_reconstruction_fails_closed_on_corrupt_persisted_evidence(string $table, string $column, mixed $value): void
    {
        $connection = $this->database(); $fixture = new NoticeFixture($connection); $fixture->record();
        $connection->update($table, [$column => $value], ['position' => 1]);
        $this->expectException(NoticeStorageFailed::class); $fixture->store->readAfter('scope', 0, 100);
    }

    public static function corruption(): iterable
    {
        yield ['fight_notices', 'payload', 'null']; yield ['fight_notice_relationships', 'payload', 'null']; yield ['fight_notices', 'payload', '{'];
        yield ['fight_notice_comparisons', 'version', 2]; yield ['fight_notice_comparisons', 'payload', '{}']; yield ['fight_notices', 'notice_id', 'different']; yield ['fight_notices', 'producer', 'different']; yield ['fight_notices', 'recorded_at', 'invalid'];
    }

    public function test_that_schema_installation_is_exclusive_idempotent_and_rejects_partial_or_unsupported_state(): void
    {
        $connection = $this->database(); $schema = new DbalNoticeSchema(); $schema->install($connection);
        foreach ([new MySQLPlatform(), new PostgreSQLPlatform()] as $platform) {
            $empty = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
            $schema->addTo($empty, $platform); self::assertCount(4, $empty->getTables());
            self::assertNotEmpty($empty->toSql($platform));
        }
        $connection->beginTransaction();
        try { $schema->install($connection); self::fail('Expected exclusive setup.'); } catch (RuntimeException) { self::assertTrue($connection->isTransactionActive()); }
        $connection->rollBack();
        $connection->update('fight_notice_state', ['version' => 2], ['singleton' => 1]);
        try { $schema->install($connection); self::fail('Expected unsupported schema.'); } catch (RuntimeException) { self::assertTrue(true); }
        $connection->executeStatement('DROP TABLE fight_notice_comparisons');
        $this->expectException(RuntimeException::class); $schema->install($connection);
    }

    public function test_that_handler_rejects_an_unrelated_command(): void
    {
        $fixture = new NoticeFixture($this->database());
        $handler = new RecordNoticeHandler(new NoticeRecorder($fixture->store, $fixture, $fixture->events));
        $command = $this->mock(Command::class); $this->expectException(InvalidArgumentException::class);
        $handler->handle(CommandMessage::create($command));
    }

    private function database(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalNoticeSchema())->install($connection);
        return $connection;
    }
}
