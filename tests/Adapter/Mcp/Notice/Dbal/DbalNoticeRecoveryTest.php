<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Notice\Dbal;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeStore;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalNoticeStore::class)]
final class DbalNoticeRecoveryTest extends UnitTestCase
{
    public function test_that_multi_scope_windows_merge_committed_order_under_one_boundary_and_empty_grants_return_no_rows(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            (new DbalNoticeSchema())->install($db); $writer = new NoticeFixture($db);
            $writer->record(['scope' => 'scope-2']); $writer->record(); $writer->record(['scope' => 'scope-2', 'id' => 'third']);
            $store = new DbalNoticeStore($db); $window = $store->readWindow(['scope', 'scope-2'], 0, 2);
            self::assertSame([1, 2], array_map(static fn ($notice) => $notice->position(), $window->notices()));
            self::assertSame(3, $window->highWater()); self::assertSame(0, $window->lostThrough());
            $empty = $store->readWindow([], 0, 100); self::assertSame([], $empty->notices()); self::assertSame($window->namespace(), $empty->namespace());
            self::assertSame(3, $empty->highWater()); self::assertFalse($db->isTransactionActive());
        } finally { $db->close(); }
    }
}
