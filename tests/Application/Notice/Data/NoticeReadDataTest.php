<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Notice\Data;

use Fight\Common\Application\Notice\Data\NoticePage;
use Fight\Common\Application\Notice\Data\NoticeSummary;
use Fight\Common\Application\Notice\Data\NoticeView;
use Fight\Common\Application\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Notice\Service\NoticeAccess;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\Notice;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(NoticePage::class)]
#[CoversClass(NoticeSummary::class)]
#[CoversClass(NoticeView::class)]
#[CoversClass(NoticeAccess::class)]
#[CoversClass(NoticeReadFailed::class)]
final class NoticeReadDataTest extends UnitTestCase
{
    public function test_that_views_and_statistics_preserve_unknown_broadcast_and_distinct_relationships(): void
    {
        $notice = Notice::fromArray(NoticeFixture::data());
        $view = new NoticeView($notice, '2026-10-25T01:02:03Z');
        self::assertSame($notice, $view->notice()); self::assertSame('2026-10-25T01:02:03.000000Z', $view->recordedAt());
        self::assertArrayNotHasKey('position', $view->toArray());
        $unknown = ['subject' => null, 'recipients' => null, 'author' => null, 'command' => null, 'event' => null, 'action' => null];
        $broadcast = array_replace($unknown, ['recipients' => []]);
        $rows = [$view, new NoticeView(Notice::fromArray(NoticeFixture::data(['relationships' => $unknown, 'occurred_at' => null])), '2026-10-24T00:00:00Z'), new NoticeView(Notice::fromArray(NoticeFixture::data(['relationships' => $broadcast, 'occurred_at' => '2026-10-26T00:00:00Z'])), '2026-10-26T00:00:00Z')];
        $data = NoticeSummary::fromNotices($rows)->toArray();
        self::assertSame(3, $data['total']); self::assertSame([['value' => 'catalog/tools', 'count' => 3]], $data['topics']);
        self::assertSame([['value' => 'catalog-changed', 'count' => 3]], $data['types']);
        self::assertSame(['named' => 1, 'broadcast' => 1, 'unknown' => 1], $data['audience']);
        self::assertSame(['subject' => 2, 'author' => 2, 'causation' => 2, 'occurrence' => 1], $data['unknown']);
        self::assertSame('2026-10-24T00:00:00.000000Z', $data['first_recorded_at']);
        self::assertSame('2026-10-26T00:00:00.000000Z', $data['last_recorded_at']);
        self::assertSame('2026-10-25T01:02:03.120000Z', $data['first_occurred_at']);
        self::assertSame('2026-10-26T00:00:00.000000Z', $data['last_occurred_at']);
        $empty = NoticeSummary::fromNotices([])->toArray(); self::assertSame(0, $empty['total']); self::assertNull($empty['first_recorded_at']); self::assertNull($empty['last_occurred_at']);
        $aliased = $view; $rowsWithReference = [&$aliased];
        $page = new NoticePage($rowsWithReference, 5, 2, 1); $aliased = $rows[1];
        self::assertSame([$view], $page->notices());
        self::assertSame(['notices' => [$view->toArray()], 'total' => 5, 'page' => 2, 'per_page' => 1], $page->toArray());
        self::assertSame(32, (new NoticePage(array_fill(0, 32, $view), 32, 1, 32))->toArray()['total']);
        $access = NoticeAccess::create(['scope'], ['topic' => 'catalog/tools']); self::assertTrue($access->selection()->matches($notice, $view->recordedAt()));
        self::assertSame([], NoticeAccess::create([])->selection()->scopes());
        $cause = new RuntimeException('concealed'); $error = new NoticeReadFailed($cause);
        self::assertSame('Retained notices could not be read completely.', $error->getMessage()); self::assertSame($cause, $error->getPrevious());
        self::assertNull((new NoticeReadFailed())->getPrevious());
    }

    #[DataProvider('dimensions')]
    public function test_that_summary_dimension_limits_are_exact_and_never_truncated(string $field): void
    {
        $rows = [];
        for ($i = 0; $i < 128; ++$i) { $rows[] = new NoticeView(Notice::fromArray(NoticeFixture::data([$field => 'value-'.$i])), '2026-10-25T00:00:00Z'); }
        self::assertCount(128, NoticeSummary::fromNotices($rows)->toArray()[$field === 'topic' ? 'topics' : 'types']);
        $rows[] = new NoticeView(Notice::fromArray(NoticeFixture::data([$field => 'overflow'])), '2026-10-25T00:00:00Z');
        $this->expectException(DomainException::class); NoticeSummary::fromNotices($rows);
    }

    public static function dimensions(): iterable { yield ['topic']; yield ['type']; }

    public function test_that_maximum_page_preserves_complete_large_notices_below_the_encoded_bound(): void
    {
        $reference = str_repeat('x', 96);
        $recipients = []; for ($i = 0; $i < 32; ++$i) { $recipients[] = str_pad('agent-'.$i, 96, 'x'); }
        $metadata = []; for ($i = 0; $i < 10; ++$i) { $metadata['app/key'.$i] = str_repeat('x', 128); }
        $notice = Notice::fromArray(NoticeFixture::data(['id' => $reference, 'producer' => $reference, 'scope' => $reference, 'topic' => $reference, 'type' => $reference, 'metadata' => $metadata, 'relationships' => ['subject' => $reference, 'recipients' => $recipients, 'author' => $reference, 'command' => $reference, 'event' => $reference, 'action' => $reference]]));
        self::assertGreaterThan(5500, strlen($notice->comparison()));
        $page = new NoticePage(array_fill(0, 32, new NoticeView($notice, '2026-10-25T00:00:00Z')), 32, 1, 32);
        self::assertLessThan(NoticePage::MAX_BYTES, strlen(json_encode($page->toArray(), JSON_THROW_ON_ERROR)));
        self::assertSame($notice->toArray()['relationships'], $page->toArray()['notices'][31]['relationships']);
    }

    public function test_that_page_results_reject_excess_rows_and_inconsistent_totals(): void
    {
        $view = new NoticeView(Notice::fromArray(NoticeFixture::data()), '2026-10-25T00:00:00Z');
        foreach ([[[$view, $view], 2, 1], [[$view], 0, 1], [[], -1, 1], [['key' => $view], 1, 1]] as [$rows, $total, $perPage]) {
            try { new NoticePage($rows, $total, 1, $perPage); self::fail('Invalid result must reject.'); } catch (DomainException) { self::assertTrue(true); }
        }
    }
}
