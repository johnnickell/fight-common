<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Closure;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Http\McpEventStream;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionAdmission;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionExecution;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Notice\Data\NoticeRecoveryBatch;
use Fight\Common\Application\Notice\Data\NoticeView;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpSubscriptionExecution::class)]
#[CoversClass(McpEventStream::class)]
final class McpSubscriptionCancellationTest extends UnitTestCase
{
    #[DataProvider('bodyCallbacks')]
    public function test_that_closing_during_a_guarded_callback_returns_no_new_bytes_and_closes_tracking(string $boundary): void
    {
        $f = $this->fixture();
        $body = $f->endpoint->handle(SubscriptionFixture::request())->getBody();
        if ($boundary === 'opening-source') {
            $f->queryPolicy = static function () use ($body): void { $body->close(); };
        } else {
            self::assertStringContainsString('acknowledged', $body->read(65536));
            (new NoticeFixture($f->db))->record(['id' => 'retained', 'type' => 'mcp.tools.list_changed']);
            $f->policy = static function ($filter, $uri, $notice) use ($body): bool {
                if ($notice !== null) { $body->close(); }
                return true;
            };
        }

        $position = $body->tell();
        self::assertSame('', $body->read(65536));
        self::assertSame($position, $body->tell());
        self::assertTrue($body->eof());
        self::assertFalse($body->isReadable());
        self::assertSame(0, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0'));
        self::assertSame($boundary === 'opening-source' ? 0 : 1, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
        self::assertSame($boundary === 'opening-source' ? 0 : 1, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        self::assertSame([], $f->diagnostics);
        $body->close();
    }

    public static function bodyCallbacks(): iterable
    {
        yield ['opening-source'];
        yield ['notice-admission'];
    }

    public function test_that_an_expired_opening_source_never_registers_or_acknowledges(): void
    {
        $f = $this->fixture();
        $body = $f->endpoint->handle(SubscriptionFixture::request())->getBody();
        $f->queryPolicy = static function (): void { usleep(1100000); };
        self::assertSame('', $body->read(65536));
        self::assertTrue($body->eof());
        self::assertSame(0, $body->tell());
        self::assertSame(0, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
        self::assertSame([], $f->diagnostics);
        $body->close();
    }

    #[DataProvider('cancellationBoundaries')]
    public function test_that_callback_stop_state_prevents_fresh_signals_and_preserves_honest_completion(string $boundary, bool $acknowledged, bool $registered, bool $expire = false): void
    {
        $f = $this->fixture();
        $execution = null;
        $events = [];
        $hit = static function (string $event) use ($boundary, &$execution, &$events, $expire): void {
            $events[] = $event;
            if ($event === $boundary) {
                if ($expire) { usleep(1100000); } else { $execution->cancel(); }
            }
        };
        $admission = new class($hit) implements McpSubscriptionAdmission {
            private int $admissions = 0;
            public function __construct(private readonly Closure $hit) {}
            public function subscriber(): string { ($this->hit)('subscriber'); return 'subscriber'; }
            public function allows(string $subscriber, string $filter, ?string $uri, ?Notice $notice): bool {
                ($this->hit)($notice !== null ? 'notice-admission' : (++$this->admissions === 1 ? 'initial-admission' : 'opening-recheck'));
                return true;
            }
        };
        $store = $this->mock(McpSubscriptionStore::class);
        $store->shouldReceive('open')->andReturnUsing(static function () use ($hit): void { $hit('open'); });
        $heartbeats = 0;
        $store->shouldReceive('heartbeat')->andReturnUsing(static function () use ($hit, &$heartbeats, $boundary): void {
            ++$heartbeats;
            $hit($heartbeats === 1 ? 'opening-heartbeat' : (in_array($boundary, ['completion-heartbeat', 'close'], true) ? 'completion-heartbeat' : match ($heartbeats) { 2 => 'source-heartbeat', 3 => 'notice-heartbeat', 4 => 'delivery-heartbeat', default => 'keepalive-heartbeat' }));
        });
        $store->shouldReceive('close')->andReturnUsing(static function () use ($hit): void { $hit('close'); });
        $queries = $this->mock(QueryBus::class);
        $reads = 0;
        $notice = new NoticeView(Notice::fromArray(NoticeFixture::data(['id' => 'retained', 'type' => 'mcp.tools.list_changed'])), '2026-10-25T00:00:00Z');
        $queries->shouldReceive('fetch')->andReturnUsing(static function () use ($hit, &$reads, $notice): NoticeRecoveryBatch {
            $hit(++$reads === 1 ? 'opening-source' : 'live-source');
            return new NoticeRecoveryBatch($reads === 1 ? [] : [$notice], 'complete', 'opaque');
        });
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request()->getBody());
        $execution = (new McpSubscriptions($queries, $store, $admission, NoticeSelection::create(['scope']), lifetimeSeconds: 1, waitMicroseconds: 10000))->execution($request);
        $frames = [];
        $execution->run(static function (string $frame) use (&$frames, $boundary): void {
            $frames[] = $frame;
            // Reach graceful completion deterministically without repeatedly fetching the same fixture batch.
            if ($boundary === 'completion-heartbeat' || $boundary === 'close') { usleep(1100000); }
        }, $f);

        self::assertContains($boundary, $events);
        self::assertCount($acknowledged ? ($expire ? 2 : 1) : 0, $frames, json_encode($events));
        if ($acknowledged) { self::assertStringContainsString('acknowledged', $frames[0]); }
        if ($acknowledged && $expire) { self::assertStringContainsString('"resultType":"complete"', $frames[1]); }
        self::assertSame($registered, in_array('open', $events, true));
        self::assertSame($registered ? 1 : 0, count(array_filter($events, static fn ($event) => $event === 'close')));
        self::assertSame([], $f->diagnostics);
        if (in_array($boundary, ['subscriber', 'initial-admission'], true)) { self::assertNotContains('opening-source', $events); }
        if ($boundary === 'source-heartbeat') { self::assertNotContains('live-source', $events); }
        if ($boundary === 'notice-heartbeat') { self::assertNotContains('notice-admission', $events); }
    }

    public static function cancellationBoundaries(): iterable
    {
        foreach (['subscriber', 'initial-admission', 'opening-source', 'open', 'opening-recheck', 'opening-heartbeat'] as $boundary) {
            yield $boundary => [$boundary, false, in_array($boundary, ['open', 'opening-recheck', 'opening-heartbeat'], true)];
            yield $boundary.'-expiry' => [$boundary, false, in_array($boundary, ['open', 'opening-recheck', 'opening-heartbeat'], true), true];
        }
        foreach (['source-heartbeat', 'live-source', 'notice-heartbeat', 'notice-admission', 'delivery-heartbeat', 'completion-heartbeat', 'close'] as $boundary) {
            yield $boundary => [$boundary, true, true];
            if (!in_array($boundary, ['completion-heartbeat', 'close'], true)) {
                yield $boundary.'-expiry' => [$boundary, true, true, true];
            }
        }
    }

    public function test_that_keepalive_tracking_cancellation_sends_no_keepalive_after_return(): void
    {
        $f = $this->fixture();
        $execution = null;
        $store = $this->mock(McpSubscriptionStore::class);
        $store->shouldReceive('open')->once();
        $store->shouldReceive('close')->once();
        $heartbeats = 0;
        $store->shouldReceive('heartbeat')->times(3)->andReturnUsing(static function () use (&$heartbeats, &$execution): void {
            if (++$heartbeats === 3) { $execution->cancel(); }
        });
        $queries = $this->mock(QueryBus::class);
        $queries->shouldReceive('fetch')->twice()->andReturn(new NoticeRecoveryBatch([], 'complete', 'opaque'));
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request()->getBody());
        $execution = (new McpSubscriptions($queries, $store, $f, NoticeSelection::create(['scope']), lifetimeSeconds: 1, waitMicroseconds: 10000))->execution($request);
        $frames = [];
        $execution->run(static function ($frame) use (&$frames): void { $frames[] = $frame; }, $f);
        self::assertCount(1, $frames);
        self::assertStringContainsString('acknowledged', $frames[0]);
        self::assertSame([], $f->diagnostics);
    }

    private function fixture(): SubscriptionFixture
    {
        return new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
    }
}
