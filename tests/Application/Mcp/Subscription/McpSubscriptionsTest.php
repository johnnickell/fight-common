<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Http\McpEventStream;
use Fight\Common\Adapter\Mcp\Http\McpRequestHandler;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Notice\Data\NoticeRecoveryBatch;
use Fight\Common\Application\Mcp\Notice\Data\NoticeView;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionExecution;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionFilter;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(McpSubscriptions::class)]
#[CoversClass(McpSubscriptionFilter::class)]
#[CoversClass(McpSubscriptionExecution::class)]
#[CoversClass(McpCapabilityRegistry::class)]
#[CoversClass(McpEventStream::class)]
#[CoversClass(McpRequestHandler::class)]
final class McpSubscriptionsTest extends UnitTestCase
{
    public function test_that_guarded_stream_acknowledges_only_current_supported_subset_and_preserves_typed_correlation(): void
    {
        $f = $this->fixture();
        $response = $f->endpoint->handle(SubscriptionFixture::request(['toolsListChanged' => true, 'resourcesListChanged' => false, 'promptsListChanged' => true, 'resourceSubscriptions' => ['file:///allowed', 'file:///hidden', 'file:///allowed']], '7'));
        self::assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));
        self::assertSame(0, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
        $body = $response->getBody();
        $ack = $this->frame($body->read(65536));
        self::assertSame('notifications/subscriptions/acknowledged', $ack['method']);
        self::assertSame('7', $ack['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        self::assertSame(['toolsListChanged' => true, 'resourceSubscriptions' => ['file:///allowed']], $ack['params']['notifications']);
        $row = $f->db->fetchAssociative('SELECT * FROM fight_mcp_subscriptions');
        $context = json_decode($row['context'], true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($context['requested']['resourcesListChanged']);
        self::assertTrue($context['requested']['promptsListChanged']);
        self::assertSame($ack['params']['notifications'], $context['accepted']);
        self::assertSame('subscriber-scope', $context['subscriber']);
        $body->close();
        self::assertGreaterThan(0, (int) $f->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        self::assertSame(['listChanged' => true], $f->registry->advertisedCapabilities()['tools']);
        self::assertSame(['subscribe' => true, 'listChanged' => true], $f->registry->advertisedCapabilities()['resources']);
    }

    public function test_that_malformed_filters_and_over_bounds_reject_before_tracking_or_query_disclosure(): void
    {
        $f = $this->fixture();
        foreach ([['unknown' => true], ['toolsListChanged' => 1], ['resourceSubscriptions' => 'x'], ['resourceSubscriptions' => ['x' => 'file:///allowed']], ['resourceSubscriptions' => array_fill(0, 17, 'file:///allowed')], ['resourceSubscriptions' => [null]], ['resourceSubscriptions' => ['relative']], ['resourceSubscriptions' => ['file:///a b']], ['resourceSubscriptions' => ['x:'.str_repeat('a', 2048)]]] as $bad) {
            $r = $f->endpoint->handle(SubscriptionFixture::request($bad));
            self::assertSame(400, $r->getStatusCode());
            self::assertSame(-32602, json_decode((string) $r->getBody(), true)['error']['code']);
        }
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request()->getBody());
        $invalid = new \Fight\Common\Application\Mcp\McpRequest(7, 'subscriptions/listen', [], $request->metadata());
        try { McpSubscriptionFilter::fromRequest($invalid); self::fail('Missing filter'); } catch (McpProtocolException) { self::assertTrue(true); }
        self::assertSame(0, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
        $f->admit = false;
        self::assertSame(429, $f->endpoint->handle(SubscriptionFixture::request())->getStatusCode());
        self::assertSame(403, $f->endpoint->handle(SubscriptionFixture::request()->withHeader('Origin', 'https://evil.test'))->getStatusCode());
    }

    public function test_that_empty_false_and_unsupported_requests_have_empty_object_acknowledgments_without_leaking_why(): void
    {
        foreach ([[], ['toolsListChanged' => false, 'resourceSubscriptions' => []], ['promptsListChanged' => true], ['resourceSubscriptions' => ['file:///hidden']]] as $filter) {
            $f = $this->fixture(); $body = $f->endpoint->handle(SubscriptionFixture::request($filter))->getBody();
            $wire = $body->read(65536);
            self::assertStringContainsString('"notifications":{}', $wire);
            $body->close();
        }
        $f = $this->fixture();
        $body = $f->endpoint->handle(SubscriptionFixture::request(['resourceSubscriptions' => array_fill(0, 16, 'file:///allowed')]))->getBody();
        self::assertSame(['resourceSubscriptions' => ['file:///allowed']], $this->frame($body->read(65536))['params']['notifications']);
        $body->close();
        $f = $this->fixture(); $f->permitted = false;
        $body = $f->endpoint->handle(SubscriptionFixture::request())->getBody();
        self::assertSame([], $this->frame($body->read(65536))['params']['notifications']); $body->close();
    }

    public function test_that_exact_live_mapping_never_copies_notice_fields_and_revocation_applies_to_queued_notices(): void
    {
        $f = $this->fixture(); $writer = new NoticeFixture($f->db);
        $writer->record(['id' => 'old', 'type' => 'mcp.tools.list_changed']);
        $body = $f->endpoint->handle(SubscriptionFixture::request(['toolsListChanged' => true, 'resourcesListChanged' => true, 'resourceSubscriptions' => ['file:///allowed']]))->getBody();
        $body->read(65536);
        foreach (['mcp.tools.list_changed', 'mcp.resources.list_changed', 'mcp.resources.updated', 'other'] as $i => $type) {
            $writer->record(['id' => 'live'.$i, 'type' => $type, 'metadata' => ['mcp/uri' => 'file:///allowed', 'app/hidden' => 'concealed-sentinel']]);
        }
        $f->policy = static fn ($filter, $uri, $notice) => $notice === null || $notice->id() !== 'denied';
        $writer->record(['id' => 'denied', 'type' => 'mcp.tools.list_changed']);
        $writer->record(['id' => 'missing-uri', 'type' => 'mcp.resources.updated', 'metadata' => []]);
        $frames = [$this->frame($body->read(65536)), $this->frame($body->read(65536)), $this->frame($body->read(65536))];
        self::assertSame(['notifications/tools/list_changed', 'notifications/resources/list_changed', 'notifications/resources/updated'], array_column($frames, 'method'));
        self::assertSame('file:///allowed', $frames[2]['params']['uri']);
        self::assertStringNotContainsString('concealed', json_encode($frames));
        self::assertSame(7, $frames[0]['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        // Already fetched queued rows must still pass current permission after a slow emitter resumes.
        $f->permitted = false;
        self::assertSame(": keepalive\n\n", $body->read(65536));
        $body->close(); self::assertSame([], $f->diagnostics);
        self::assertSame(7, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
    }

    public function test_that_normal_lifetime_completes_after_tracking_closure_and_unknown_families_are_not_enabled(): void
    {
        $f = $this->fixture();
        $body = $f->endpoint->handle(SubscriptionFixture::request())->getBody();
        $wire = $body->getContents();
        $lines = array_values(array_filter(explode("\n", $wire), static fn ($line) => str_starts_with($line, 'data: ')));
        self::assertCount(2, $lines);
        $last = $this->frame($lines[1]);
        self::assertSame(['jsonrpc' => '2.0', 'id' => 7, 'result' => ['resultType' => 'complete', '_meta' => ['io.modelcontextprotocol/subscriptionId' => 7]]], $last);
        self::assertGreaterThan(0, (int) $f->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        self::assertSame([], $f->diagnostics);
        new McpSubscriptions($f->queries, $f->store, $f, NoticeSelection::create(['scope']), lifetimeSeconds: 120, waitMicroseconds: 1000000, maxFrameBytes: 65536);
        $onlyTools = new McpSubscriptions($f->queries, $f->store, $f, NoticeSelection::create(['scope']), resources: false);
        self::assertSame(['toolsListChanged'], $onlyTools->supported());
        $onlyResources = new McpSubscriptions($f->queries, $f->store, $f, NoticeSelection::create(['scope']), tools: false);
        self::assertSame(['resourcesListChanged', 'resourceSubscriptions'], $onlyResources->supported());
        self::assertSame(null, (new McpCapabilityRegistry(new McpServerInfo('test', '1'), []))->subscriptions());
        try { new McpCapabilityRegistry(new McpServerInfo('test', '1'), [], $onlyTools); self::fail('Missing serving family'); } catch (DomainException) { self::assertTrue(true); }
        foreach ([['tools' => false, 'resources' => false], ['lifetimeSeconds' => 0], ['lifetimeSeconds' => 121], ['waitMicroseconds' => 9999], ['waitMicroseconds' => 1000001], ['maxFrameBytes' => 255], ['maxFrameBytes' => 65537]] as $limits) {
            try { new McpSubscriptions($f->queries, $f->store, $f, NoticeSelection::create(['scope']), ...$limits); self::fail('Invalid limits'); } catch (DomainException) { self::assertTrue(true); }
        }
    }

    public function test_that_source_permission_tracking_and_frame_failures_end_without_fake_success_or_private_output(): void
    {
        foreach (['source', 'uncertain', 'incomplete', 'wrong-result', 'open', 'heartbeat', 'close', 'diagnostics', 'permission', 'frame'] as $scenario) {
            $f = $this->fixture();
            $store = $this->mock(McpSubscriptionStore::class);
            $store->shouldReceive('open')->andReturnUsing(static function () use ($scenario): void { if ($scenario === 'open') { throw new RuntimeException('private'); } });
            $store->shouldReceive('heartbeat')->andReturnUsing(static function () use ($scenario): void { if ($scenario === 'heartbeat') { throw new RuntimeException('private'); } });
            $store->shouldReceive('close')->andReturnUsing(static function () use ($scenario): void { if ($scenario === 'close') { throw new RuntimeException('private'); } });
            $queries = $this->mock(QueryBus::class);
            $queries->shouldReceive('fetch')->andReturnUsing(static function () use ($scenario) {
                if (in_array($scenario, ['source', 'diagnostics'], true)) { throw new RuntimeException('private'); }
                if ($scenario === 'wrong-result') { return null; }
                return new NoticeRecoveryBatch([], in_array($scenario, ['uncertain', 'incomplete'], true) ? $scenario : 'complete', 'opaque');
            });
            $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request(id: str_repeat('雪', $scenario === 'frame' ? 1000 : 1))->getBody());
            $calls = 0;
            if ($scenario === 'permission') { $f->policy = static function () use (&$calls) { return ++$calls === 1; }; }
            $diagnostics = $scenario === 'diagnostics' ? new class implements \Fight\Common\Application\Mcp\McpDiagnostics { public function record(\Throwable $failure): void { throw new RuntimeException('private diagnostics'); } } : $f;
            $execution = (new McpSubscriptions($queries, $store, $f, NoticeSelection::create(['scope']), lifetimeSeconds: 1, waitMicroseconds: 10000, maxFrameBytes: 256))->execution($request);
            $wire = '';
            $execution->run(static function ($frame) use (&$wire, $execution, $scenario) { $wire .= $frame; if ($scenario === 'close') { $execution->cancel(); } }, $diagnostics);
            $execution->run(static function () { self::fail('Not single-use'); }, $diagnostics);
            self::assertStringNotContainsString('private', $wire);
            self::assertStringNotContainsString('"resultType"', $wire);
            if ($scenario !== 'diagnostics') { self::assertNotEmpty($f->diagnostics, $scenario); }
        }
        $f = $this->fixture(); $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request()->getBody());
        $execution = $f->subscriptions->execution($request); $execution->cancel();
        $execution->run(static function () { self::fail('Cancelled execution'); }, $f);
    }

    public function test_that_cancellation_and_permission_changes_between_queued_frames_are_observed_before_next_disclosure(): void
    {
        foreach (['cancel', 'revoke'] as $mode) {
            $f = $this->fixture(); $queries = $this->mock(QueryBus::class);
            $rows = [];
            foreach (['one', 'two'] as $id) { $rows[] = new NoticeView(Notice::fromArray(NoticeFixture::data(['id' => $id, 'type' => 'mcp.tools.list_changed'])), '2026-10-25T00:00:00Z'); }
            $queries->shouldReceive('fetch')->andReturn(new NoticeRecoveryBatch([], 'complete', 'start'), new NoticeRecoveryBatch($rows, 'complete', 'next'));
            $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request()->getBody());
            $execution = (new McpSubscriptions($queries, $f->store, $f, NoticeSelection::create(['scope']), lifetimeSeconds: 1, waitMicroseconds: 10000))->execution($request);
            $frames = [];
            $execution->run(static function ($frame) use (&$frames, $execution, $f, $mode) {
                if (str_starts_with($frame, 'data: ') && (json_decode(substr($frame, 6), true)['method'] ?? null) === 'notifications/tools/list_changed') {
                    $frames[] = $frame;
                    if ($mode === 'cancel') { $execution->cancel(); } else { $f->permitted = false; }
                } elseif ($frame === ": keepalive\n\n") { $execution->cancel(); }
            }, $f);
            self::assertCount(1, $frames, $mode.' '.json_encode($f->diagnostics)); self::assertSame([], $f->diagnostics);
        }
    }

    public function test_that_actual_escaped_frame_size_accepts_exact_budget_and_rejects_one_byte_over(): void
    {
        $id = str_repeat('雪', 30); $f = $this->fixture();
        $body = $f->endpoint->handle(SubscriptionFixture::request(id: $id))->getBody();
        $wire = $body->read(65536); $body->close(); $size = strlen($wire);
        self::assertGreaterThan(256, $size);
        foreach ([$size, $size - 1] as $budget) {
            $f = new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), frameBytes: $budget);
            $body = $f->endpoint->handle(SubscriptionFixture::request(id: $id))->getBody();
            $actual = $body->read(65536); $body->close();
            self::assertSame($budget === $size ? $wire : '', $actual);
            self::assertCount($budget === $size ? 0 : 1, $f->diagnostics);
        }
    }

    public function test_that_a_transport_stall_beyond_freshness_cannot_resume_as_healthy_or_send_completion(): void
    {
        $f = $this->fixture(); $body = $f->endpoint->handle(SubscriptionFixture::request())->getBody();
        self::assertSame('notifications/subscriptions/acknowledged', $this->frame($body->read(65536))['method']);
        // Deterministic shared-clock expiry simulates the state encountered after a blocked transport resumes.
        $f->db->executeStatement('UPDATE fight_mcp_subscriptions SET expires_at = ?', [time()]);
        self::assertSame('', $body->read(65536));
        self::assertTrue($body->eof()); self::assertNotEmpty($f->diagnostics);
        self::assertSame(0, (int) $f->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        $body->close();
    }

    private function fixture(): SubscriptionFixture { return new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])); }
    private function frame(string $wire): array { self::assertStringStartsWith('data: ', $wire); return json_decode(trim(substr($wire, 6)), true, flags: JSON_THROW_ON_ERROR); }
}
