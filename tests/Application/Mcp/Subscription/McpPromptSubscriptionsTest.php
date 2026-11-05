<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Application\Mcp as ReleasedMcp;
use Fight\Common\Application\Mcp\Capability\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\Composition\McpCompositionValidator;
use Fight\Common\Application\Mcp\Protocol\McpRequestDecoder;
use Fight\Common\Application\Mcp\Protocol\McpServerInfo;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionExecution;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(McpSubscriptions::class)]
#[CoversClass(McpSubscriptionExecution::class)]
#[CoversClass(ReleasedMcp\McpCapabilityRegistry::class)]
#[CoversClass(McpCompositionValidator::class)]
final class McpPromptSubscriptionsTest extends UnitTestCase
{
    public function test_that_prompt_enablement_requires_serving_and_never_changes_ordinary_disabled_prompt_serving(): void
    {
        foreach ([false, true] as $enabled) {
            $fixture = $this->fixture($enabled);
            self::assertSame(['listChanged' => $enabled], $fixture->registry->advertisedCapabilities()['prompts']);
            foreach ([[], ['promptsListChanged' => false], ['promptsListChanged' => true]] as $requested) {
                $body = $fixture->endpoint->handle(SubscriptionFixture::request($requested))->getBody();
                $ack = $this->frame($body->read(65536)); $body->close();
                self::assertSame($enabled && ($requested['promptsListChanged'] ?? false) ? ['promptsListChanged' => true] : [], $ack['params']['notifications']);
            }
            self::assertSame([], $fixture->visiblePrompt->calls); self::assertSame([], $fixture->hiddenPrompt->calls);
            self::assertSame(1, $fixture->visiblePrompt->metadataCalls);
        }
        $fixture = $this->fixture(true);
        $promptSubscriptionComposition = new McpSubscriptions($fixture->queryBus, $fixture->subscriptionStore, $fixture, NoticeSelection::create(['scope']), tools: false, resources: false, prompts: true);
        self::assertSame(['promptsListChanged'], $promptSubscriptionComposition->supported());
        try { new McpCapabilityRegistry(new McpServerInfo('test', '1'), [], $promptSubscriptionComposition); self::fail('Absent Prompts'); }
        catch (DomainException) { self::assertTrue(true); }
        $registry = new McpCapabilityRegistry(new McpServerInfo('test', '1'), [$fixture->registry->capabilityFor('prompts/list')], $promptSubscriptionComposition);
        self::assertSame(['prompts' => ['listChanged' => true]], $registry->advertisedCapabilities());
    }

    public function test_that_whole_signal_permission_is_independent_of_visible_prompts_and_rechecks_every_queued_notice(): void
    {
        $fixture = $this->fixture(true); $writer = new NoticeFixture($fixture->connection);
        $body = $fixture->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true, 'toolsListChanged' => true, 'resourcesListChanged' => false], '7'))->getBody();
        $ack = $this->frame($body->read(65536));
        self::assertSame(['promptsListChanged' => true, 'toolsListChanged' => true], $ack['params']['notifications']);
        foreach (['hidden', 'visible', 'queued', 'lookalike', 'unrequested'] as $id) {
            $writer->record(['id' => $id, 'type' => match ($id) { 'lookalike' => 'notifications/prompts/list_changed', 'unrequested' => 'mcp.resources.list_changed', default => 'mcp.prompts.list_changed' }, 'metadata' => ['app/private' => 'PRIVATE BODY ARGUMENT NAME']]);
        }
        $checks = [];
        $fixture->policy = static function ($filter, $uri, $notice) use (&$checks): bool { if ($notice !== null) { $checks[] = $notice->id(); } return $notice === null || $notice->id() !== 'hidden'; };
        self::assertSame(['jsonrpc' => '2.0', 'method' => 'notifications/prompts/list_changed', 'params' => ['_meta' => ['io.modelcontextprotocol/subscriptionId' => '7']]], $this->frame($body->read(65536)));
        $fixture->permitted = false;
        self::assertSame(": keepalive\n\n", $body->read(65536)); $body->close();
        self::assertContains('hidden', $checks); self::assertContains('visible', $checks);
        self::assertSame([], $fixture->visiblePrompt->calls); self::assertSame([], $fixture->hiddenPrompt->calls);
        self::assertSame(1, $fixture->visiblePrompt->metadataCalls);
        self::assertSame(5, (int) $fixture->connection->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        self::assertSame([], $fixture->diagnostics);
        $fixture = $this->fixture(true); $fixture->policy = static fn () => false;
        $body = $fixture->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true]))->getBody();
        self::assertSame([], $this->frame($body->read(65536))['params']['notifications']); $body->close();
        self::assertTrue($fixture->promptPermitted, 'Retrieval permission alone never grants catalog activity disclosure');
    }

    public function test_that_disabled_unrequested_and_finally_cancelled_prompt_changes_never_disclose_or_invoke_content(): void
    {
        foreach ([false, true] as $enabled) {
            $fixture = $this->fixture($enabled);
            $body = $fixture->endpoint->handle(SubscriptionFixture::request($enabled ? ['toolsListChanged' => true] : ['promptsListChanged' => true]))->getBody();
            $body->read(65536);
            (new NoticeFixture($fixture->connection))->record(['id' => 'unrequested', 'type' => 'mcp.prompts.list_changed']);
            self::assertSame(": keepalive\n\n", $body->read(65536)); $body->close();
            self::assertSame([], $fixture->visiblePrompt->calls);
        }
        $fixture = $this->fixture(true);
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request(['promptsListChanged' => true])->getBody());
        $execution = $fixture->subscriptionComposition->execution($request); $calls = 0;
        $fixture->policy = static function ($filter, $uri, $notice) use ($execution, &$calls): bool { if ($notice !== null && ++$calls === 2) { $execution->cancel(); } return true; };
        $frames = [];
        $execution->run(static function ($frame) use (&$frames, $fixture): void { $frames[] = $frame; (new NoticeFixture($fixture->connection))->record(['id' => 'cancelled', 'type' => 'mcp.prompts.list_changed']); }, $fixture);
        self::assertCount(1, $frames); self::assertSame(2, $calls); self::assertSame([], $fixture->visiblePrompt->calls);
    }

    public function test_that_an_idle_wait_crossing_the_lifetime_completes_without_new_keepalive_or_prompt_disclosure(): void
    {
        $fixture = $this->fixture(true);
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request(['promptsListChanged' => true])->getBody());
        $execution = (new McpSubscriptions($fixture->queryBus, $fixture->subscriptionStore, $fixture, NoticeSelection::create(['scope']), lifetimeSeconds: 1, waitMicroseconds: 1000000, prompts: true))->execution($request);
        $frames = [];
        $execution->run(static function ($frame) use (&$frames): void { $frames[] = $frame; }, $fixture);
        self::assertCount(2, $frames);
        self::assertSame('notifications/subscriptions/acknowledged', $this->frame($frames[0])['method']);
        self::assertSame('complete', $this->frame($frames[1])['result']['resultType']);
        self::assertSame([], $fixture->visiblePrompt->calls); self::assertSame([], $fixture->diagnostics);
    }

    public function test_that_tracking_callbacks_cannot_leave_a_stale_notification_permission_before_emission(): void
    {
        $fixture = $this->fixture(true); $admitted = false;
        $fixture->policy = static function ($filter, $uri, $notice) use (&$admitted): bool { if ($notice !== null) { $admitted = true; } return true; };
        $subscriptionStore = $this->mock(McpSubscriptionStore::class);
        $subscriptionStore->shouldReceive('open')->andReturnUsing($fixture->subscriptionStore->open(...));
        $subscriptionStore->shouldReceive('close')->andReturnUsing($fixture->subscriptionStore->close(...));
        $subscriptionStore->shouldReceive('heartbeat')->andReturnUsing(static function (...$args) use ($fixture, &$admitted): void { $fixture->subscriptionStore->heartbeat(...$args); if ($admitted) { $fixture->permitted = false; } });
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request(['promptsListChanged' => true])->getBody());
        $execution = (new McpSubscriptions($fixture->queryBus, $subscriptionStore, $fixture, NoticeSelection::create(['scope']), lifetimeSeconds: 1, waitMicroseconds: 10000, prompts: true))->execution($request);
        $frames = [];
        $execution->run(static function ($frame) use (&$frames, $fixture, $execution): void {
            $frames[] = $frame;
            if (count($frames) === 1) { (new NoticeFixture($fixture->connection))->record(['id' => 'retained', 'type' => 'mcp.prompts.list_changed']); }
            else { $execution->cancel(); }
        }, $fixture);
        self::assertCount(2, $frames); self::assertSame(": keepalive\n\n", $frames[1]);
        self::assertSame([], $fixture->visiblePrompt->calls);
    }

    public function test_that_prompt_policy_failures_and_escaped_frame_bounds_preserve_retained_notices_without_fake_completion(): void
    {
        $id = str_repeat('雪"', 30); $fixture = $this->fixture(true);
        $body = $fixture->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true], $id))->getBody();
        $ack = $body->read(65536);
        (new NoticeFixture($fixture->connection))->record(['id' => 'first', 'type' => 'mcp.prompts.list_changed']);
        $notification = $body->read(65536); $body->close();
        // A mixed acknowledgment can be larger than a catalog signal; measure the actual signal independently.
        self::assertStringNotContainsString('first', $notification);
        foreach ([strlen($ack), strlen($ack) - 1] as $budget) {
            $fixture = $this->fixture(true, $budget);
            $body = $fixture->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true], $id))->getBody();
            self::assertSame($budget === strlen($ack) ? $ack : '', $body->read(65536)); $body->close();
            self::assertCount($budget === strlen($ack) ? 0 : 1, $fixture->diagnostics);
        }
        $fixture = $this->fixture(true);
        $body = $fixture->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true]))->getBody(); $body->read(65536);
        (new NoticeFixture($fixture->connection))->record(['id' => 'retained', 'type' => 'mcp.prompts.list_changed']);
        $fixture->policy = static function (): bool { throw new RuntimeException('PRIVATE POLICY'); };
        self::assertSame('', $body->read(65536)); self::assertTrue($body->eof()); $body->close();
        self::assertNotEmpty($fixture->diagnostics); self::assertSame(1, (int) $fixture->connection->fetchOne('SELECT COUNT(*) FROM fight_notices'));
    }

    public function test_that_malformed_retained_prompt_data_closes_without_disclosure_or_success_and_keeps_the_record(): void
    {
        $fixture = $this->fixture(true);
        $body = $fixture->endpoint->handle(SubscriptionFixture::request(['promptsListChanged' => true]))->getBody(); $body->read(65536);
        (new NoticeFixture($fixture->connection))->record(['id' => 'malformed', 'type' => 'mcp.prompts.list_changed']);
        $fixture->connection->executeStatement('UPDATE fight_notices SET payload = ?', ['{"type":"mcp.prompts.list_changed","metadata":{"app/private":"PRIVATE MALFORMED"}}']);
        self::assertSame('', $body->read(65536)); self::assertTrue($body->eof()); $body->close();
        self::assertNotEmpty($fixture->diagnostics); self::assertSame(1, (int) $fixture->connection->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        self::assertSame([], $fixture->visiblePrompt->calls); self::assertSame([], $fixture->hiddenPrompt->calls);
    }

    private function fixture(bool $enabled, int $budget = 16384): SubscriptionFixture { return new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), frameBytes: $budget, prompts: $enabled, servePrompts: true); }
    private function frame(string $wire): array { self::assertStringStartsWith('data: ', $wire); return json_decode(trim(substr($wire, 6)), true, flags: JSON_THROW_ON_ERROR); }
}
