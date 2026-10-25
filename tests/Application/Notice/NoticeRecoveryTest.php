<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Notice;

use Fight\Common\Adapter\Notice\SodiumNoticeContinuationProtector;
use Fight\Common\Application\Notice\Data\NoticeRecoveryBatch;
use Fight\Common\Application\Notice\Data\NoticeRecoveryWindow;
use Fight\Common\Application\Notice\Data\NoticeView;
use Fight\Common\Application\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Notice\QueryHandler\RecoverNoticesHandler;
use Fight\Common\Application\Notice\Service\NoticeAccess;
use Fight\Common\Application\Notice\Service\NoticeContinuationProtector;
use Fight\Common\Application\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Notice\Service\NoticeRecovery;
use Fight\Common\Application\Notice\Service\NoticeRecoveryIdentity;
use Fight\Common\Application\Notice\Service\NoticeStore;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Notice\Query\RecoverNotices;
use Fight\Common\Domain\Notice\RecordedNotice;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NoticeAccess::class)]
#[CoversClass(NoticeRecovery::class)]
#[CoversClass(NoticeRecoveryBatch::class)]
#[CoversClass(NoticeRecoveryIdentity::class)]
#[CoversClass(RecoverNoticesHandler::class)]
#[CoversClass(RecoverNotices::class)]
final class NoticeRecoveryTest extends UnitTestCase
{
    private const string NS = '0123456789abcdef0123456789abcdef';

    public function test_that_exact_query_serialization_and_finite_bounds_preserve_selection_without_authority(): void
    {
        $selection = NoticeSelection::create(['scope']);
        $query = new RecoverNotices($selection, str_repeat('a', 2048), 32, 100);
        self::assertSame($query->toArray(), RecoverNotices::fromArray($query->toArray())->toArray());
        self::assertSame($selection, $query->selection());
        self::assertSame(32, $query->batchSize()); self::assertSame(100, $query->scanLimit());
        self::assertSame(str_repeat('a', 2048), $query->continuation());
        foreach ([[null, 0, 100], [null, 33, 100], [null, 1, 0], [null, 1, 101], ['', 1, 1], [str_repeat('a', 2049), 1, 1]] as $args) {
            try { new RecoverNotices($selection, ...$args); self::fail('Bounds must reject'); } catch (DomainException) { self::assertTrue(true); }
        }
        foreach ([['selection' => []], array_replace($query->toArray(), ['selection' => 'bad']), array_replace($query->toArray(), ['continuation' => 1]), array_replace($query->toArray(), ['batch_size' => '1']), array_replace($query->toArray(), ['scan_limit' => null])] as $data) {
            try { RecoverNotices::fromArray($data); self::fail('Types must reject'); } catch (DomainException) { self::assertTrue(true); }
        }
    }

    public function test_that_scanned_boundaries_never_advance_over_the_first_omitted_eligible_notice(): void
    {
        $store = $this->mock(NoticeStore::class); $codec = $this->codec();
        $store->shouldReceive('readWindow')->once()->with(['scope'], 0, 100)->andReturn(new NoticeRecoveryWindow(self::NS, 4, 0, [$this->row(1), $this->row(2, 'other'), $this->row(3), $this->row(4)]));
        $store->shouldReceive('readWindow')->once()->with(['scope'], 2, 100)->andReturn(new NoticeRecoveryWindow(self::NS, 4, 0, [$this->row(3), $this->row(4)]));
        $recovery = new NoticeRecovery($store, $codec);
        $selection = NoticeSelection::create(['scope'], ['topic' => 'catalog/tools']);
        $first = $recovery->recover(new RecoverNotices($selection, batchSize: 1), $this->access());
        self::assertSame('incomplete', $first->status()); self::assertSame('n1', $first->notices()[0]->notice()->id());
        $next = $recovery->recover(new RecoverNotices($selection, $first->continuation(), 1), $this->access());
        self::assertSame('n3', $next->notices()[0]->notice()->id());
        self::assertSame('incomplete', $next->status());
    }

    public function test_that_full_scan_budget_is_incomplete_unless_the_committed_boundary_was_actually_exhausted(): void
    {
        $store = $this->mock(NoticeStore::class); $codec = $this->codec();
        $store->shouldReceive('readWindow')->with(['scope'], 0, 1)->andReturn(new NoticeRecoveryWindow(self::NS, 5, 0, [$this->row(1, 'other')]));
        $result = (new NoticeRecovery($store, $codec))->recover(new RecoverNotices(NoticeSelection::create(['scope'], ['topic' => 'catalog/tools']), scanLimit: 1), $this->access());
        self::assertSame('incomplete', $result->status()); self::assertSame([], $result->notices());
        self::assertSame(1, json_decode($codec->open($result->continuation()), true)['position']);
        $store = $this->mock(NoticeStore::class);
        $store->shouldReceive('readWindow')->with(['scope'], 0, 1)->andReturn(new NoticeRecoveryWindow(self::NS, 1, 0, [$this->row(1)]));
        self::assertSame('complete', (new NoticeRecovery($store, $codec))->recover(new RecoverNotices(NoticeSelection::create(['scope']), scanLimit: 1), $this->access())->status());
    }

    public function test_that_empty_and_loss_outcomes_require_real_continuity_and_uncertainty_hides_global_loss(): void
    {
        foreach ([0, 7] as $loss) {
            $store = $this->mock(NoticeStore::class);
            $store->shouldReceive('readWindow')->andReturn(new NoticeRecoveryWindow(self::NS, 7, $loss, []));
            $recovery = new NoticeRecovery($store, $this->codec()); $query = new RecoverNotices(NoticeSelection::create(['scope']));
            $first = $recovery->recover($query, $this->access());
            self::assertSame($loss === 0 ? 'complete' : 'incomplete', $first->status());
            self::assertSame($first->status(), $recovery->recover(new RecoverNotices($query->selection(), $first->continuation()), $this->access())->status());
            foreach ([new NoticeRecoveryIdentity('caller', null, true), new NoticeRecoveryIdentity('caller', 'rev', false)] as $identity) {
                self::assertSame('uncertain', $recovery->recover($query, NoticeAccess::create(['scope'], recoveryIdentity: $identity))->status());
            }
        }
    }

    public function test_that_missing_identity_invalid_configuration_and_oversized_single_notice_fail_explicitly(): void
    {
        $store = $this->mock(NoticeStore::class); $codec = $this->codec();
        foreach ([[0, 65536], [901, 65536], [300, 2047], [300, 262145]] as $args) {
            try { new NoticeRecovery($store, $codec, ...$args); self::fail('Invalid configuration'); } catch (DomainException) { self::assertTrue(true); }
        }
        try { (new NoticeRecovery($store, $codec))->recover(new RecoverNotices(NoticeSelection::create(['scope'])), NoticeAccess::create(['scope'])); self::fail('No trusted caller'); } catch (DomainException) { self::assertTrue(true); }
        $large = new RecordedNotice(Notice::fromArray(NoticeFixture::data(['metadata' => ['app/label' => str_repeat('雪', 40)]])), 1, '2026-10-25T00:00:00.000000Z');
        $store->shouldReceive('readWindow')->once()->andReturn(new NoticeRecoveryWindow(self::NS, 1, 0, [$large]));
        $this->expectException(DomainException::class); $this->expectExceptionMessage('Notice recovery result budget exceeded.');
        (new NoticeRecovery($store, $codec, maxResultBytes: 2048))->recover(new RecoverNotices(NoticeSelection::create(['scope'])), $this->access());
    }

    public function test_that_invalid_stale_expired_or_changed_context_state_rejects_before_returning_continuation(): void
    {
        $store = $this->mock(NoticeStore::class); $codec = $this->codec();
        $store->shouldReceive('readWindow')->andReturn(new NoticeRecoveryWindow(self::NS, 2, 0, []));
        $recovery = new NoticeRecovery($store, $codec); $selection = NoticeSelection::create(['scope']);
        $first = $recovery->recover(new RecoverNotices($selection), $this->access());
        $state = json_decode($codec->open($first->continuation()), true);
        foreach ([['version' => 2], ['binding' => 'wrong'], ['namespace' => 'wrong'], ['position' => -1], ['position' => RecordedNotice::MAX_POSITION + 1], ['position' => 3], ['namespace' => str_repeat('a', 32)], ['loss' => null], ['expires' => time() - 1], ['expires' => time() + 901]] as $change) {
            try { $recovery->recover(new RecoverNotices($selection, $codec->seal(json_encode(array_replace($state, $change), JSON_THROW_ON_ERROR))), $this->access()); self::fail('Invalid protected state'); } catch (DomainException) { self::assertTrue(true); }
        }
        foreach (['null', '{bad', str_repeat('x', 1024)] as $plain) {
            $protector = $this->mock(NoticeContinuationProtector::class); $protector->shouldReceive('open')->once()->andReturn($plain);
            try { (new NoticeRecovery($store, $protector))->recover(new RecoverNotices($selection, 'token'), $this->access()); self::fail('Malformed state'); } catch (\Throwable $e) { self::assertNotSame('Malformed state', $e->getMessage()); }
        }
        foreach ([NoticeAccess::create(['scope'], recoveryIdentity: new NoticeRecoveryIdentity('other', 'revision', true)), NoticeAccess::create(['scope'], recoveryIdentity: new NoticeRecoveryIdentity('caller', 'changed', true)), NoticeAccess::create([] , recoveryIdentity: new NoticeRecoveryIdentity('caller', 'revision', true))] as $access) {
            try { $recovery->recover(new RecoverNotices($selection, $first->continuation()), $access); self::fail('Context mismatch'); } catch (DomainException) { self::assertTrue(true); }
        }
    }

    public function test_that_inconsistent_storage_rows_and_public_result_bounds_fail_closed_and_arrays_are_isolated(): void
    {
        foreach ([[$this->row(1), $this->row(1)], [$this->row(3)], [new RecordedNotice(Notice::fromArray(NoticeFixture::data(['scope' => 'scope-2'])), 1, '2026-10-25T00:00:00.000000Z')]] as $rows) {
            $store = $this->mock(NoticeStore::class); $store->shouldReceive('readWindow')->once()->andReturn(new NoticeRecoveryWindow(self::NS, 2, 0, $rows));
            try { (new NoticeRecovery($store, $this->codec()))->recover(new RecoverNotices(NoticeSelection::create(['scope'])), $this->access()); self::fail('Inconsistent rows'); } catch (DomainException) { self::assertTrue(true); }
        }
        $view = new NoticeView($this->row(1)->notice(), $this->row(1)->recordedAt());
        $item = $view; $batch = new NoticeRecoveryBatch([&$item], 'complete', 'opaque'); $item = new NoticeView($this->row(2)->notice(), $this->row(2)->recordedAt());
        self::assertSame([$view], $batch->notices()); self::assertSame('opaque', $batch->continuation()); self::assertSame('n1', $batch->toArray()['notices'][0]['id']);
        foreach ([[array_fill(0, 33, $view), 'complete', 'token'], [['x' => $view], 'complete', 'token'], [[], 'expired', 'token'], [[], 'complete', ''], [[], 'complete', str_repeat('x', 2049)]] as $args) {
            try { new NoticeRecoveryBatch(...$args); self::fail('Invalid projection'); } catch (DomainException) { self::assertTrue(true); }
        }
    }

    public function test_that_query_handler_reauthorizes_before_recovery_and_wraps_private_failures(): void
    {
        self::assertSame(RecoverNotices::class, RecoverNoticesHandler::queryRegistration());
        $admission = $this->mock(NoticeReadAdmission::class); $store = $this->mock(NoticeStore::class);
        $admission->shouldReceive('currentAccess')->twice()->andReturn($this->access(), NoticeAccess::create([]));
        $store->shouldReceive('readWindow')->once()->with(['scope'], 0, 100)->andReturn(new NoticeRecoveryWindow(self::NS, 0, 0, []));
        $handler = new RecoverNoticesHandler($admission, new NoticeRecovery($store, $this->codec()));
        $message = QueryMessage::create(new RecoverNotices(NoticeSelection::create(['scope'])));
        self::assertSame('complete', $handler->handle($message)->status());
        $this->expectException(NoticeReadFailed::class); $this->expectExceptionMessage('Retained notices could not be read completely.');
        $handler->handle($message);
    }

    private function row(int $position, string $topic = 'catalog/tools'): RecordedNotice { return new RecordedNotice(Notice::fromArray(NoticeFixture::data(['id' => 'n'.$position, 'topic' => $topic])), $position, '2026-10-25T00:00:00.000000Z'); }
    private function access(): NoticeAccess { return NoticeAccess::create(['scope'], recoveryIdentity: new NoticeRecoveryIdentity('caller', 'revision', true)); }
    private function codec(): SodiumNoticeContinuationProtector { return new SodiumNoticeContinuationProtector(['testkey1' => str_repeat('k', 32)], 'testkey1'); }
}
