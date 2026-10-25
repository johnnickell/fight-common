<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeStore;
use Fight\Common\Application\Notice\CommandHandler\RemoveNoticesHandler;
use Fight\Common\Application\Notice\Data\NoticeRecoveryWindow;
use Fight\Common\Application\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Application\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Application\Notice\Service\NoticeRemover;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Notice\Command\RemoveNotices;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeRemovalSelection;
use Fight\Common\Domain\Notice\RecordedNotice;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Notice\NoticeRemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DbalNoticeStore::class)]
#[CoversClass(DbalNoticeSchema::class)]
#[CoversClass(NoticeRecoveryWindow::class)]
#[CoversClass(NoticeRemover::class)]
#[CoversClass(RemoveNoticesHandler::class)]
#[CoversClass(NoticeRemovalRejected::class)]
final class DbalNoticeRemovalTest extends UnitTestCase
{
    public function test_that_subset_removal_cascades_and_preserves_survivors_then_empty_feed_loss_and_high_water(): void
    {
        $connection = $this->database(); $fixture = new NoticeRemovalFixture($connection);
        $fixture->recording->record(); $fixture->recording->record(['id' => 'notice-2']);
        $before = $fixture->recording->store->readWindow('scope', 0, 100);
        self::assertTrue($before->hasUnbrokenHistoryAfter($before->namespace(), 0));
        $survivor = $before->notices()[1];
        $selection = $fixture->selection([NoticeRemovalFixture::target()]);
        self::assertSame(RemoveNotices::class, RemoveNoticesHandler::commandRegistration());
        $fixture->remove($selection); $this->assertRemovalRejected(fn () => $fixture->remove($selection));
        self::assertSame(2, $fixture->admissions); self::assertSame(1, $fixture->retainedAdmissions);
        $window = (new DbalNoticeStore($connection))->readWindow('scope', 0, 100);
        self::assertSame(1, $window->lostThrough()); self::assertSame(2, $window->highWater());
        self::assertFalse($window->hasUnbrokenHistoryAfter($before->namespace(), 0));
        self::assertTrue($window->hasUnbrokenHistoryAfter($before->namespace(), 1));
        self::assertFalse($window->hasUnbrokenHistoryAfter(str_repeat('0', 32), 2));
        self::assertFalse($window->hasUnbrokenHistoryAfter($before->namespace(), 3));
        self::assertSame($survivor->notice()->comparison(), $window->notices()[0]->notice()->comparison());
        self::assertSame($survivor->recordedAt(), $window->notices()[0]->recordedAt());
        self::assertSame(2, $window->notices()[0]->position());
        foreach (array_slice(DbalNoticeSchema::TABLES, 1) as $table) { self::assertSame([2], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table))); }
        $fixture->remove($fixture->selection([NoticeRemovalFixture::target(2, 'notice-2')]));
        $empty = (new DbalNoticeStore($connection))->readWindow('scope', 0, 100);
        self::assertSame([], $empty->notices()); self::assertSame(2, $empty->lostThrough()); self::assertSame(2, $empty->highWater());
        self::assertSame($before->namespace(), $empty->namespace());
        $fixture->recording->record();
        self::assertSame(3, $fixture->recording->store->readAfter('scope', 0, 100)[0]->position());
        $this->assertRemovalRejected(fn () => $fixture->remove($selection));
        self::assertCount(1, $fixture->recording->store->readAfter('scope', 0, 100), 'Old position must not remove a new same-ID incarnation.');
        try { $fixture->recording->record(['topic' => 'changed']); self::fail('Retained comparison must conflict.'); } catch (NoticeConflict) { self::assertTrue(true); }
    }

    public function test_that_every_target_and_relationship_is_admitted_before_any_delete_and_repeats_reauthorize(): void
    {
        $connection = $this->database(); $fixture = new NoticeRemovalFixture($connection);
        $fixture->recording->record(['relationships' => ['subject' => null, 'recipients' => [], 'author' => null, 'command' => null, 'event' => null, 'action' => null]]);
        $fixture->recording->record(['id' => 'notice-2', 'relationships' => ['subject' => 'other-recipient', 'recipients' => [], 'author' => null, 'command' => null, 'event' => null, 'action' => null]]);
        $selection = $fixture->selection([NoticeRemovalFixture::target(), NoticeRemovalFixture::target(2, 'notice-2')]);
        $fixture->deniedReferences = ['other-recipient'];
        try { $fixture->remove($selection); self::fail('Denied relationship must roll back entire selection.'); } catch (NoticeRemovalRejected $failure) { self::assertSame('Notice removal rejected.', $failure->getMessage()); }
        self::assertCount(2, $fixture->recording->store->readAfter('scope', 0, 100));
        $fixture->deniedReferences = []; $fixture->recording->allowed = false;
        $fixture->remove($selection); // Independent removal permission does not require recording permission.
        $fixture->allowed = false;
        foreach ([$selection, $fixture->selection([NoticeRemovalFixture::target(20, 'missing')])] as $request) {
            try { $fixture->remove($request); self::fail('Repeat/missing still require permission.'); } catch (NoticeRemovalRejected) { self::assertSame(2, (int) $connection->fetchOne('SELECT lost_through FROM fight_notice_state')); }
        }
        $fixture->allowed = true;
        $this->assertRemovalRejected(fn () => $fixture->remove($fixture->selection([NoticeRemovalFixture::target(20, 'missing')])));
        $foreign = NoticeRemovalSelection::fromArray(array_replace($selection->toArray(), ['namespace' => str_repeat('0', 32)]));
        $this->expectException(NoticeRemovalRejected::class); $fixture->remove($foreign);
    }

    public function test_that_wrong_scope_or_producer_does_not_remove_a_matching_position(): void
    {
        $connection = $this->database(); $fixture = new NoticeRemovalFixture($connection); $fixture->recording->record();
        $selection = $fixture->selection([NoticeRemovalFixture::target(1, 'notice-1', 'scope-2', 'producer-2')]);
        $this->assertRemovalRejected(fn () => $fixture->remove($selection)); self::assertCount(1, $fixture->recording->store->readAfter('scope', 0, 100));
        self::assertSame(0, $fixture->recording->store->readWindow('scope', 0, 100)->lostThrough());
    }

    public function test_that_unowned_removal_and_read_window_inside_outer_transaction_reject_without_cleanup(): void
    {
        $connection = $this->database(); $fixture = new NoticeRemovalFixture($connection); $fixture->recording->record();
        $selection = $fixture->selection([NoticeRemovalFixture::target()]);
        try { $fixture->recording->store->remove($selection); self::fail('Must own transaction.'); } catch (LogicException) { self::assertFalse($connection->isTransactionActive()); }
        $connection->executeStatement('CREATE TABLE notice_removal_caller (id INTEGER)');
        $connection->beginTransaction(); $connection->insert('notice_removal_caller', ['id' => 42]);
        foreach ([fn () => $fixture->remove($selection), fn () => $fixture->recording->store->readWindow('scope', 0, 100)] as $operation) {
            try { $operation(); self::fail('Must reject enclosing work.'); } catch (LogicException) { self::assertTrue($connection->isTransactionActive()); self::assertSame(42, (int) $connection->fetchOne('SELECT id FROM notice_removal_caller')); }
        }
        self::assertSame(0, $fixture->admissions); $connection->rollBack();
    }

    public function test_that_history_write_failure_restores_notice_relationship_comparison_and_loss_state(): void
    {
        $connection = $this->database(); $fixture = new NoticeRemovalFixture($connection); $fixture->recording->record();
        $selection = $fixture->selection([NoticeRemovalFixture::target()]);
        $connection->executeStatement("CREATE TRIGGER removal_fail BEFORE UPDATE OF lost_through ON fight_notice_state BEGIN SELECT RAISE(ABORT, 'private'); END");
        try { $fixture->remove($selection); self::fail('Expected rollback.'); } catch (NoticeStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); }
        self::assertCount(1, $fixture->recording->store->readAfter('scope', 0, 100));
        self::assertSame(0, (int) $connection->fetchOne('SELECT lost_through FROM fight_notice_state'));
        $fixture->recording->record(); // Comparison also survived rollback.
    }

    public function test_that_corrupt_or_absent_history_evidence_never_becomes_complete_empty_history(): void
    {
        $connection = $this->database(); $fixture = new NoticeRemovalFixture($connection);
        $connection->update('fight_notice_state', ['lost_through' => 1], ['singleton' => 1]);
        try { $fixture->recording->store->readWindow('scope', 0, 100); self::fail('Corrupt state must fail.'); } catch (NoticeStorageFailed $failure) { self::assertInstanceOf(DomainException::class, $failure->getPrevious()); }
        $proxy = $this->mock(Connection::class);
        $proxy->shouldReceive('getDatabasePlatform')->andReturn(new PostgreSQLPlatform());
        $proxy->shouldReceive('getTransactionIsolation')->andReturn(TransactionIsolationLevel::READ_COMMITTED);
        $proxy->shouldReceive('isTransactionActive')->andReturn(false);
        $proxy->shouldReceive('beginTransaction')->once(); $proxy->shouldReceive('executeStatement')->once();
        $proxy->shouldReceive('fetchOne')->once()->andReturn(1); $proxy->shouldReceive('fetchAssociative')->once()->andReturn(false); $proxy->shouldReceive('rollBack')->once();
        $this->expectException(NoticeStorageFailed::class); (new DbalNoticeStore($proxy))->readWindow('scope', 0, 100);
    }

    public function test_that_handler_rejects_unrelated_command_and_window_bounds_validate_before_storage(): void
    {
        $fixture = new NoticeRemovalFixture($this->database());
        $handler = new RemoveNoticesHandler(new NoticeRemover($fixture->recording->store, $fixture));
        try { $handler->handle(CommandMessage::create($this->mock(Command::class))); self::fail('Wrong command.'); } catch (InvalidArgumentException) { self::assertSame(0, $fixture->admissions); }
        $this->expectException(DomainException::class); $fixture->recording->store->readWindow('scope', 0, 101);
    }

    #[DataProvider('invalidEvidence')]
    public function test_that_invalid_internal_window_evidence_rejects(string $namespace, int $highWater, int $lostThrough, array $rows): void
    {
        $this->expectException(DomainException::class); new NoticeRecoveryWindow($namespace, $highWater, $lostThrough, $rows);
    }

    public static function invalidEvidence(): iterable
    {
        yield ['invalid', 0, 0, []]; yield [str_repeat('a', 32), 0, -1, []]; yield [str_repeat('a', 32), 0, 1, []]; yield [str_repeat('a', 32), 2147483648, 0, []]; yield [str_repeat('a', 32), 0, 0, ['bad-key' => null]]; yield [str_repeat('a', 32), 0, 0, array_fill(0, 101, null)];
    }

    public function test_that_recovery_window_cannot_be_changed_by_external_php_array_references(): void
    {
        $record = new RecordedNotice(Notice::fromArray(NoticeFixture::data()), 1, '2026-10-25T00:00:00.000000Z');
        $rows = [&$record];
        $window = new NoticeRecoveryWindow(str_repeat('a', 32), 2, 0, $rows);
        $record = new RecordedNotice(Notice::fromArray(NoticeFixture::data()), 2, '2026-10-25T00:00:00.000000Z');
        self::assertSame(1, $window->notices()[0]->position(), 'Coherent snapshot must not change after construction.');
        $copy = $window->notices(); $copy[0] = $record;
        self::assertSame(1, $window->notices()[0]->position());
    }

    public function test_that_concealed_unknown_and_stale_targets_share_one_failure_and_preflight_preserves_all_rows(): void
    {
        $connection = $this->database(); $fixture = new NoticeRemovalFixture($connection); $fixture->recording->record();
        $fixture->deniedReferences = ['subject-agent'];
        foreach ([NoticeRemovalFixture::target(), NoticeRemovalFixture::target(2, 'missing'), NoticeRemovalFixture::target(2)] as $target) {
            $this->assertRemovalRejected(fn () => $fixture->remove($fixture->selection([$target])));
        }
        $fixture->deniedReferences = [];
        $selection = $fixture->selection([NoticeRemovalFixture::target(), NoticeRemovalFixture::target(2, 'missing')]);
        $this->assertRemovalRejected(fn () => $fixture->remove($selection));
        self::assertCount(1, $fixture->recording->store->readAfter('scope', 0, 100));
        self::assertSame(0, (int) $connection->fetchOne('SELECT lost_through FROM fight_notice_state'));
        $missing = $fixture->selection([NoticeRemovalFixture::target(2, 'missing')]);
        $this->assertRemovalRejected(fn () => $fixture->recording->store->commitTransactional(fn () => $fixture->recording->store->remove($missing)));
    }

    #[DataProvider('malformedPositions')]
    public function test_that_malformed_sqlite_loss_evidence_cannot_be_cast_into_complete_history(string $column, mixed $value): void
    {
        $connection = $this->database(); $connection->update('fight_notice_state', [$column => $value], ['singleton' => 1]);
        $this->expectException(NoticeStorageFailed::class); (new DbalNoticeStore($connection))->readWindow('scope', 0, 100);
    }

    public static function malformedPositions(): iterable
    {
        foreach (['last_position', 'lost_through'] as $column) {
            foreach (['unavailable', 0.5, -1, 2147483648] as $value) { yield [$column, $value]; }
        }
    }

    public function test_that_retired_namespace_rejects_even_an_exact_live_tuple_and_position(): void
    {
        $connection = $this->database(); $fixture = new NoticeRemovalFixture($connection); $fixture->recording->record();
        $oldSelection = $fixture->selection([NoticeRemovalFixture::target()]);
        $connection->update('fight_notice_state', ['namespace' => str_repeat('0', 32), 'lost_through' => 1], ['singleton' => 1]);
        $this->assertRemovalRejected(fn () => $fixture->remove($oldSelection));
        foreach (array_slice(DbalNoticeSchema::TABLES, 1) as $table) { self::assertSame([1], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table))); }
        $window = $fixture->recording->store->readWindow('scope', 0, 100);
        self::assertSame(1, $window->highWater()); self::assertSame(1, $window->lostThrough());
        self::assertFalse($window->hasUnbrokenHistoryAfter($oldSelection->namespace(), 1));
        $fixture->remove($fixture->selection([NoticeRemovalFixture::target()]));
        self::assertSame([], $fixture->recording->store->readAfter('scope', 0, 100));
    }

    public function test_that_deleting_below_an_existing_loss_boundary_never_decrements_it(): void
    {
        $connection = $this->database(); $fixture = new NoticeRemovalFixture($connection);
        $fixture->recording->record(); $fixture->recording->record(['id' => 'notice-2']);
        $fixture->remove($fixture->selection([NoticeRemovalFixture::target(2, 'notice-2')]));
        $fixture->remove($fixture->selection([NoticeRemovalFixture::target()]));
        $window = $fixture->recording->store->readWindow('scope', 0, 100);
        self::assertSame(2, $window->lostThrough()); self::assertSame(2, $window->highWater()); self::assertSame([], $window->notices());
    }

    private function assertRemovalRejected(callable $operation): void
    {
        try { $operation(); self::fail('Missing, stale and denied targets must share rejection.'); }
        catch (NoticeRemovalRejected $failure) { self::assertSame('Notice removal rejected.', $failure->getMessage()); }
    }

    private function database(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalNoticeSchema())->install($connection); return $connection;
    }
}
