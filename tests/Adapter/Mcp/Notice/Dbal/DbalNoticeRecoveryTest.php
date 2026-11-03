<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeRecoveryReader;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(DbalNoticeRecoveryReader::class)]
final class DbalNoticeRecoveryTest extends UnitTestCase
{
    public function test_that_lost_read_commit_acknowledgment_closes_only_that_reader_and_fresh_recovery_still_works(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            (new DbalNoticeSchema())->install($connection);
            $writer = new NoticeFixture($connection); $writer->record();
            $proxy = $this->mock(Connection::class);
            foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'isAutoCommit', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'fetchAllAssociative'] as $method) {
                $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $connection->$method(...$arguments));
            }
            $failure = new RuntimeException('private lost read commit acknowledgment');
            $proxy->shouldReceive('commit')->once()->andReturnUsing(static function () use ($connection, $failure): void {
                $connection->commit(); throw $failure;
            });
            $noticeRecoveryReader = new DbalNoticeRecoveryReader($proxy);
            try { $noticeRecoveryReader->readWindow('scope', 0, 100); self::fail('Unacknowledged read transaction must fail.'); }
            catch (NoticeStorageFailed $caught) { self::assertTrue($caught->outcomeUncertain()); self::assertSame($failure, $caught->getPrevious()); }
            foreach ([fn () => $noticeRecoveryReader->readWindow('scope', 0, 100), fn () => $noticeRecoveryReader->readAfter('scope', 0, 100)] as $operation) {
                try { $operation(); self::fail('Closed reader must reject.'); } catch (LogicException) { self::assertFalse($connection->isTransactionActive()); }
            }
            $freshReader = new DbalNoticeRecoveryReader($connection);
            $window = $freshReader->readWindow('scope', 0, 100);
            self::assertSame(1, $window->highWater()); self::assertSame(0, $window->lostThrough());
            self::assertSame(1, $window->notices()[0]->position());
            self::assertSame(1, $writer->admissions);
        } finally { $connection->close(); }
    }

    public function test_that_multi_scope_windows_merge_committed_order_under_one_boundary_and_empty_grants_return_no_rows(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            (new DbalNoticeSchema())->install($db); $writer = new NoticeFixture($db);
            $writer->record(['scope' => 'scope-2']); $writer->record(); $writer->record(['scope' => 'scope-2', 'id' => 'third']);
            $noticeRecoveryReader = new DbalNoticeRecoveryReader($db); $window = $noticeRecoveryReader->readWindow(['scope', 'scope-2'], 0, 2);
            self::assertSame([1, 2], array_map(static fn ($notice) => $notice->position(), $window->notices()));
            self::assertSame(3, $window->highWater()); self::assertSame(0, $window->lostThrough());
            $empty = $noticeRecoveryReader->readWindow([], 0, 100); self::assertSame([], $empty->notices()); self::assertSame($window->namespace(), $empty->namespace());
            self::assertSame(3, $empty->highWater()); self::assertFalse($db->isTransactionActive());
        } finally { $db->close(); }
    }
}
