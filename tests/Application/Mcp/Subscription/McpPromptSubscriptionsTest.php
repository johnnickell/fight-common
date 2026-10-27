<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionExecution;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(McpSubscriptions::class)]
#[CoversClass(McpSubscriptionExecution::class)]
#[CoversClass(McpCapabilityRegistry::class)]
final class McpPromptSubscriptionsTest extends UnitTestCase
{
    public function test_that_prompt_enablement_requires_serving_and_never_changes_ordinary_disabled_prompt_serving(): void
    {
        foreach ([false, true] as $enabled) {
            $f = $this->fixture($enabled);
            self::assertSame(['listChanged' => $enabled], $f->registry->advertisedCapabilities()['prompts']);
            foreach ([[], ['promptsListChanged' => false], ['promptsListChanged' => true]] as $requested) {
                $body = $f->endpoint->handle(SubscriptionFixture::request($requested))->getBody();
                $ack = $this->frame($body->read(65536)); $body->close();
                self::assertSame($enabled && ($requested['promptsListChanged'] ?? false) ? ['promptsListChanged' => true] : [], $ack['params']['notifications']);
            }
            self::assertSame([], $f->visiblePrompt->calls); self::assertSame([], $f->hiddenPrompt->calls);
            self::assertSame(1, $f->visiblePrompt->metadataCalls);
        }
        $f = $this->fixture(true);
        $only = new McpSubscriptions($f->queries, $f->store, $f, NoticeSelection::create(['scope']), tools: false, resources: false, prompts: true);
        self::assertSame(['promptsListChanged'], $only->supported());
        try { new McpCapabilityRegistry(new McpServerInfo('test', '1'), [], $only); self::fail('Absent Prompts'); }
        catch (DomainException) { self::assertTrue(true); }
        $registry = new McpCapabilityRegistry(new McpServerInfo('test', '1'), [$f->registry->capabilityFor('prompts/list')], $only);
        self::assertSame(['prompts' => ['listChanged' => true]], $registry->advertisedCapabilities());
    }

    public function test_that_whole_signal_permission_is_independent_of_visible_prompts_and_rechecks_every_queued_notice(): void
    {
        $f = $this->fixture(true); $writer = new NoticeFixture($f->db);
        $body = $f->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true, 'toolsListChanged' => true, 'resourcesListChanged' => false], '7'))->getBody();
        $ack = $this->frame($body->read(65536));
        self::assertSame(['promptsListChanged' => true, 'toolsListChanged' => true], $ack['params']['notifications']);
        foreach (['hidden', 'visible', 'queued', 'lookalike', 'unrequested'] as $id) {
            $writer->record(['id' => $id, 'type' => match ($id) { 'lookalike' => 'notifications/prompts/list_changed', 'unrequested' => 'mcp.resources.list_changed', default => 'mcp.prompts.list_changed' }, 'metadata' => ['app/private' => 'PRIVATE BODY ARGUMENT NAME']]);
        }
        $checks = [];
        $f->policy = static function ($filter, $uri, $notice) use (&$checks): bool { if ($notice !== null) { $checks[] = $notice->id(); } return $notice === null || $notice->id() !== 'hidden'; };
        self::assertSame(['jsonrpc' => '2.0', 'method' => 'notifications/prompts/list_changed', 'params' => ['_meta' => ['io.modelcontextprotocol/subscriptionId' => '7']]], $this->frame($body->read(65536)));
        $f->permitted = false;
        self::assertSame(": keepalive\n\n", $body->read(65536)); $body->close();
        self::assertContains('hidden', $checks); self::assertContains('visible', $checks);
        self::assertSame([], $f->visiblePrompt->calls); self::assertSame([], $f->hiddenPrompt->calls);
        self::assertSame(1, $f->visiblePrompt->metadataCalls);
        self::assertSame(5, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        self::assertSame([], $f->diagnostics);
        $f = $this->fixture(true); $f->policy = static fn () => false;
        $body = $f->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true]))->getBody();
        self::assertSame([], $this->frame($body->read(65536))['params']['notifications']); $body->close();
        self::assertTrue($f->promptPermitted, 'Retrieval permission alone never grants catalog activity disclosure');
    }

    public function test_that_disabled_unrequested_and_finally_cancelled_prompt_changes_never_disclose_or_invoke_content(): void
    {
        foreach ([false, true] as $enabled) {
            $f = $this->fixture($enabled);
            $body = $f->endpoint->handle(SubscriptionFixture::request($enabled ? ['toolsListChanged' => true] : ['promptsListChanged' => true]))->getBody();
            $body->read(65536);
            (new NoticeFixture($f->db))->record(['id' => 'unrequested', 'type' => 'mcp.prompts.list_changed']);
            self::assertSame(": keepalive\n\n", $body->read(65536)); $body->close();
            self::assertSame([], $f->visiblePrompt->calls);
        }
        $f = $this->fixture(true);
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request(['promptsListChanged' => true])->getBody());
        $execution = $f->subscriptions->execution($request); $calls = 0;
        $f->policy = static function ($filter, $uri, $notice) use ($execution, &$calls): bool { if ($notice !== null && ++$calls === 2) { $execution->cancel(); } return true; };
        $frames = [];
        $execution->run(static function ($frame) use (&$frames, $f): void { $frames[] = $frame; (new NoticeFixture($f->db))->record(['id' => 'cancelled', 'type' => 'mcp.prompts.list_changed']); }, $f);
        self::assertCount(1, $frames); self::assertSame(2, $calls); self::assertSame([], $f->visiblePrompt->calls);
    }

    public function test_that_an_idle_wait_crossing_the_lifetime_completes_without_new_keepalive_or_prompt_disclosure(): void
    {
        $f = $this->fixture(true);
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request(['promptsListChanged' => true])->getBody());
        $execution = (new McpSubscriptions($f->queries, $f->store, $f, NoticeSelection::create(['scope']), lifetimeSeconds: 1, waitMicroseconds: 1000000, prompts: true))->execution($request);
        $frames = [];
        $execution->run(static function ($frame) use (&$frames): void { $frames[] = $frame; }, $f);
        self::assertCount(2, $frames);
        self::assertSame('notifications/subscriptions/acknowledged', $this->frame($frames[0])['method']);
        self::assertSame('complete', $this->frame($frames[1])['result']['resultType']);
        self::assertSame([], $f->visiblePrompt->calls); self::assertSame([], $f->diagnostics);
    }

    public function test_that_tracking_callbacks_cannot_leave_a_stale_notification_permission_before_emission(): void
    {
        $f = $this->fixture(true); $admitted = false;
        $f->policy = static function ($filter, $uri, $notice) use (&$admitted): bool { if ($notice !== null) { $admitted = true; } return true; };
        $store = $this->mock(McpSubscriptionStore::class);
        $store->shouldReceive('open')->andReturnUsing($f->store->open(...));
        $store->shouldReceive('close')->andReturnUsing($f->store->close(...));
        $store->shouldReceive('heartbeat')->andReturnUsing(static function (...$args) use ($f, &$admitted): void { $f->store->heartbeat(...$args); if ($admitted) { $f->permitted = false; } });
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request(['promptsListChanged' => true])->getBody());
        $execution = (new McpSubscriptions($f->queries, $store, $f, NoticeSelection::create(['scope']), lifetimeSeconds: 1, waitMicroseconds: 10000, prompts: true))->execution($request);
        $frames = [];
        $execution->run(static function ($frame) use (&$frames, $f, $execution): void {
            $frames[] = $frame;
            if (count($frames) === 1) { (new NoticeFixture($f->db))->record(['id' => 'retained', 'type' => 'mcp.prompts.list_changed']); }
            else { $execution->cancel(); }
        }, $f);
        self::assertCount(2, $frames); self::assertSame(": keepalive\n\n", $frames[1]);
        self::assertSame([], $f->visiblePrompt->calls);
    }

    public function test_that_prompt_policy_failures_and_escaped_frame_bounds_preserve_retained_notices_without_fake_completion(): void
    {
        $id = str_repeat('雪"', 30); $f = $this->fixture(true);
        $body = $f->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true], $id))->getBody();
        $ack = $body->read(65536);
        (new NoticeFixture($f->db))->record(['id' => 'first', 'type' => 'mcp.prompts.list_changed']);
        $notification = $body->read(65536); $body->close();
        // A mixed acknowledgment can be larger than a catalog signal; measure the actual signal independently.
        self::assertStringNotContainsString('first', $notification);
        foreach ([strlen($ack), strlen($ack) - 1] as $budget) {
            $f = $this->fixture(true, $budget);
            $body = $f->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true], $id))->getBody();
            self::assertSame($budget === strlen($ack) ? $ack : '', $body->read(65536)); $body->close();
            self::assertCount($budget === strlen($ack) ? 0 : 1, $f->diagnostics);
        }
        $f = $this->fixture(true);
        $body = $f->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true]))->getBody(); $body->read(65536);
        (new NoticeFixture($f->db))->record(['id' => 'retained', 'type' => 'mcp.prompts.list_changed']);
        $f->policy = static function (): bool { throw new RuntimeException('PRIVATE POLICY'); };
        self::assertSame('', $body->read(65536)); self::assertTrue($body->eof()); $body->close();
        self::assertNotEmpty($f->diagnostics); self::assertSame(1, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
    }

    public function test_that_malformed_retained_prompt_data_closes_without_disclosure_or_success_and_keeps_the_record(): void
    {
        $f = $this->fixture(true);
        $body = $f->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true]))->getBody(); $body->read(65536);
        (new NoticeFixture($f->db))->record(['id' => 'malformed', 'type' => 'mcp.prompts.list_changed']);
        $f->db->executeStatement('UPDATE fight_notices SET payload = ?', ['{"type":"mcp.prompts.list_changed","metadata":{"app/private":"PRIVATE MALFORMED"}}']);
        self::assertSame('', $body->read(65536)); self::assertTrue($body->eof()); $body->close();
        self::assertNotEmpty($f->diagnostics); self::assertSame(1, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        self::assertSame([], $f->visiblePrompt->calls); self::assertSame([], $f->hiddenPrompt->calls);
    }

    private function fixture(bool $enabled, int $budget = 16384): SubscriptionFixture { return new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), frameBytes: $budget, prompts: $enabled, servePrompts: true); }
    private function frame(string $wire): array { self::assertStringStartsWith('data: ', $wire); return json_decode(trim(substr($wire, 6)), true, flags: JSON_THROW_ON_ERROR); }
}
