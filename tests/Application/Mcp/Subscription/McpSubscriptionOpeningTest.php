<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionExecution;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpSubscriptionExecution::class)]
final class McpSubscriptionOpeningTest extends UnitTestCase
{
    #[DataProvider('openingRevocations')]
    public function test_that_revocation_inside_valid_opening_tracking_closes_without_obsolete_acknowledgment(array $requested, string $revoked, bool $prompts): void
    {
        $f = new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), prompts: $prompts, servePrompts: $prompts);
        $tracked = false;
        $f->policy = static function ($filter) use (&$tracked, $revoked): bool { return !$tracked || $filter !== $revoked; };
        $store = $this->mock(McpSubscriptionStore::class);
        $store->shouldReceive('open')->once()->andReturnUsing($f->store->open(...));
        $store->shouldReceive('heartbeat')->once()->andReturnUsing(static function (...$args) use ($f, &$tracked): void {
            $f->store->heartbeat(...$args);
            $tracked = true;
        });
        $store->shouldReceive('close')->once()->andReturnUsing($f->store->close(...));
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request($requested, 'opening')->getBody());
        $execution = (new McpSubscriptions($f->queries, $store, $f, NoticeSelection::create(['scope']), prompts: $prompts))->execution($request);
        $frames = [];
        $execution->run(static function ($frame) use (&$frames, $execution): void {
            $frames[] = $frame;
            $execution->cancel();
        }, $f);

        self::assertTrue($tracked, 'The revocation follows a valid returned tracking update');
        self::assertSame([], $frames, 'Opening changes close rather than acknowledging an obsolete or replacement subset');
        self::assertSame(1, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at > 0'));
        $context = json_decode($f->db->fetchOne('SELECT context FROM fight_mcp_subscriptions'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($requested, $context['requested']);
        self::assertSame($requested, $context['accepted']);
        self::assertCount(1, $f->diagnostics);
        self::assertSame([], $f->visiblePrompt->calls);
        self::assertSame([], $f->hiddenPrompt->calls);
    }

    #[DataProvider('finalAdmissionStops')]
    public function test_that_final_opening_admission_cannot_outlive_cancellation_deadline_or_tracking(string $stop): void
    {
        $f = new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), prompts: true, servePrompts: true);
        $tracked = false;
        $execution = null;
        $f->policy = static function () use (&$tracked, &$execution, $stop): bool {
            if ($tracked) {
                if ($stop === 'cancel') { $execution->cancel(); }
                else { usleep($stop === 'freshness' ? 5100000 : 1100000); }
            }
            return true;
        };
        $store = $this->mock(McpSubscriptionStore::class);
        $store->shouldReceive('open')->once()->andReturnUsing($f->store->open(...));
        $store->shouldReceive('heartbeat')->once()->andReturnUsing(static function (...$args) use ($f, &$tracked): void {
            $f->store->heartbeat(...$args);
            $tracked = true;
        });
        $store->shouldReceive('close')->once()->andReturnUsing($f->store->close(...));
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request(['promptsListChanged' => true])->getBody());
        $execution = (new McpSubscriptions($f->queries, $store, $f, NoticeSelection::create(['scope']), lifetimeSeconds: $stop === 'freshness' ? 20 : 1, prompts: true))->execution($request);
        $frames = [];
        $execution->run(static function ($frame) use (&$frames, $execution): void { $frames[] = $frame; $execution->cancel(); }, $f);

        self::assertTrue($tracked);
        self::assertSame([], $frames);
        self::assertSame(1, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at > 0'));
        self::assertCount($stop === 'freshness' ? 1 : 0, $f->diagnostics);
        self::assertSame([], $f->visiblePrompt->calls);
        self::assertSame([], $f->hiddenPrompt->calls);
    }

    public static function finalAdmissionStops(): iterable
    {
        yield 'cancellation' => ['cancel'];
        yield 'monotonic deadline' => ['deadline'];
        yield 'independent lease expiry' => ['freshness'];
    }

    public static function openingRevocations(): iterable
    {
        yield 'Prompt only' => [['promptsListChanged' => true], 'promptsListChanged', true];
        yield 'mixed Prompt revocation' => [['toolsListChanged' => true, 'promptsListChanged' => true, 'resourcesListChanged' => true], 'promptsListChanged', true];
        yield 'ordinary Tool' => [['toolsListChanged' => true], 'toolsListChanged', false];
        yield 'ordinary Resource catalog' => [['resourcesListChanged' => true], 'resourcesListChanged', false];
        yield 'ordinary selected Resource' => [['resourceSubscriptions' => ['file:///allowed']], 'resourceSubscriptions', false];
    }
}
