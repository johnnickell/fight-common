<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeReader;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeAccess;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;
use Fight\Common\Domain\Mcp\Notice\Query\ListNotices;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalNoticeReader::class)]
final class DbalNoticeReaderTest extends UnitTestCase
{
    public function test_that_reader_counts_full_filtered_selection_in_bounded_batches_and_returns_only_the_requested_page(): void
    {
        $connection = $this->database(); $fixture = new NoticeFixture($connection);
        for ($i = 0; $i < 105; ++$i) { $fixture->record(['id' => 'notice-'.$i, 'topic' => $i % 2 === 0 ? 'even' : 'odd']); }
        $fixture->record(['id' => 'notice-0', 'topic' => 'even']);
        $connection->executeStatement("UPDATE fight_notices SET recorded_at = '2026-10-25T00:00:00.000000Z'");
        $reader = new DbalNoticeReader($connection); $selection = NoticeSelection::create(['scope']); $access = NoticeAccess::create(['scope']);
        $before = $connection->fetchAllAssociative('SELECT * FROM fight_notices ORDER BY position');
        self::assertSame(105, $reader->summarize($selection, $access)->toArray()['total']);
        $page = $reader->list(new ListNotices($selection, 2, 2), $access);
        self::assertSame(['notice-102', 'notice-101'], array_map(static fn ($view) => $view->notice()->id(), $page->notices()));
        self::assertSame(105, $page->toArray()['total']);
        self::assertSame(['notice-0', 'notice-1'], array_map(static fn ($view) => $view->notice()->id(), $reader->list(new ListNotices($selection, 1, 2, ['recorded_at' => 'ASC']), $access)->notices()));
        $filtered = NoticeSelection::create(['scope'], ['topic' => 'even']);
        self::assertSame(53, $reader->summarize($filtered, $access)->toArray()['total']);
        self::assertSame(0, $reader->summarize($filtered, NoticeAccess::create(['scope'], ['topic' => 'odd']))->toArray()['total']);
        self::assertSame(1, $reader->list(new ListNotices($selection), NoticeAccess::create(['scope'], ['id' => 'notice-1', 'producer' => 'producer']))->toArray()['total']);
        self::assertSame('notice-0', $reader->inspect(new GetNotice('scope', 'producer', 'notice-0'), $access)->notice()->id());
        self::assertNull($reader->inspect(new GetNotice('scope', 'producer', 'missing'), $access));
        self::assertNull($reader->inspect(new GetNotice('scope', 'producer', 'notice-0'), NoticeAccess::create([])));
        self::assertSame([], $reader->list(new ListNotices($selection, 1000, 1), $access)->notices());
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_notices ORDER BY position'));
        $connection->close();
    }

    public function test_that_storage_corruption_and_failure_are_explicit_without_private_diagnostics_or_empty_success(): void
    {
        $connection = $this->database(); (new NoticeFixture($connection))->record();
        $reader = new DbalNoticeReader($connection); $selection = NoticeSelection::create(['scope']); $access = NoticeAccess::create(['scope']);
        foreach (['wrong-identity', 'bad-json', 'missing-relationships', 'noncanonical-time', 'dirty-isolation', 'caller-transaction', 'missing-table'] as $failure) {
            if ($failure === 'wrong-identity') { $connection->update('fight_notices', ['notice_id' => 'wrong'], ['position' => 1]); }
            elseif ($failure === 'bad-json') { $connection->update('fight_notices', ['payload' => '{private'], ['position' => 1]); }
            elseif ($failure === 'missing-relationships') { $connection->delete('fight_notice_relationships', ['position' => 1]); }
            elseif ($failure === 'noncanonical-time') { $connection->update('fight_notices', ['recorded_at' => '2026-10-25T00:00:00Z'], ['position' => 1]); }
            elseif ($failure === 'dirty-isolation') { $connection->setTransactionIsolation(TransactionIsolationLevel::READ_UNCOMMITTED); }
            elseif ($failure === 'caller-transaction') { $connection->beginTransaction(); }
            else { $connection->executeStatement('DROP TABLE fight_notice_comparisons'); $connection->executeStatement('DROP TABLE fight_notice_relationships'); }
            foreach ([static fn () => $reader->summarize($selection, $access), static fn () => $reader->list(new ListNotices($selection), $access), static fn () => $reader->inspect(new GetNotice('scope', 'producer', $failure === 'wrong-identity' ? 'wrong' : 'notice-1'), $access)] as $operation) {
                try { $operation(); self::fail('Incomplete read must fail.'); } catch (NoticeReadFailed $caught) { self::assertSame('Retained notices could not be read completely.', $caught->getMessage()); self::assertNotNull($caught->getPrevious()); }
            }
            if ($failure === 'caller-transaction') { self::assertTrue($connection->isTransactionActive()); $connection->rollBack(); }
            if ($failure === 'dirty-isolation') { $connection->setTransactionIsolation(TransactionIsolationLevel::SERIALIZABLE); }
            if ($failure === 'missing-table') { break; }
            $connection->update('fight_notices', ['notice_id' => 'notice-1', 'recorded_at' => '2026-10-25T00:00:00.000000Z', 'payload' => json_encode(array_diff_key(NoticeFixture::data(), ['relationships' => true]), JSON_THROW_ON_ERROR)], ['position' => 1]);
            if ($failure === 'missing-relationships') { $connection->insert('fight_notice_relationships', ['position' => 1, 'payload' => json_encode(NoticeFixture::data()['relationships'], JSON_THROW_ON_ERROR)]); }
        }
        $connection->close();
    }

    public function test_that_unsupported_database_platform_is_rejected_without_running_sql(): void
    {
        $connection = $this->mock(Connection::class); $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new OraclePlatform());
        $this->expectException(InvalidArgumentException::class); new DbalNoticeReader($connection);
    }

    private function database(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]); (new DbalNoticeSchema())->install($connection); return $connection;
    }
}
