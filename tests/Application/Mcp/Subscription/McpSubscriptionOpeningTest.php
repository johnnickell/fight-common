<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Application\Mcp\Protocol\McpRequestDecoder;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionExecution;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
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
        $fixture = new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), prompts: $prompts, servePrompts: $prompts);
        $tracked = false;
        $fixture->policy = static function ($filter) use (&$tracked, $revoked): bool { return !$tracked || $filter !== $revoked; };
        $subscriptionStore = $this->mock(McpSubscriptionStore::class);
        $subscriptionStore->shouldReceive('open')->once()->andReturnUsing($fixture->subscriptionStore->open(...));
        $subscriptionStore->shouldReceive('heartbeat')->once()->andReturnUsing(static function (...$args) use ($fixture, &$tracked): void {
            $fixture->subscriptionStore->heartbeat(...$args);
            $tracked = true;
        });
        $subscriptionStore->shouldReceive('close')->once()->andReturnUsing($fixture->subscriptionStore->close(...));
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request($requested, 'opening')->getBody());
        $execution = (new McpSubscriptions($fixture->queryBus, $subscriptionStore, $fixture, NoticeSelection::create(['scope']), prompts: $prompts))->execution($request);
        $frames = [];
        $execution->run(static function ($frame) use (&$frames, $execution): void {
            $frames[] = $frame;
            $execution->cancel();
        }, $fixture);

        self::assertTrue($tracked, 'The revocation follows a valid returned tracking update');
        self::assertSame([], $frames, 'Opening changes close rather than acknowledging an obsolete or replacement subset');
        self::assertSame(1, (int) $fixture->connection->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at > 0'));
        $context = json_decode($fixture->connection->fetchOne('SELECT context FROM fight_mcp_subscriptions'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($requested, $context['requested']);
        self::assertSame($requested, $context['accepted']);
        self::assertCount(1, $fixture->diagnostics);
        self::assertSame([], $fixture->visiblePrompt->calls);
        self::assertSame([], $fixture->hiddenPrompt->calls);
    }

    #[DataProvider('finalAdmissionStops')]
    public function test_that_final_opening_admission_cannot_outlive_cancellation_deadline_or_tracking(string $stop): void
    {
        $fixture = new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), prompts: true, servePrompts: true);
        $tracked = false;
        $execution = null;
        $fixture->policy = static function () use (&$tracked, &$execution, $stop): bool {
            if ($tracked) {
                if ($stop === 'cancel') { $execution->cancel(); }
                else { usleep($stop === 'freshness' ? 5100000 : 1100000); }
            }
            return true;
        };
        $subscriptionStore = $this->mock(McpSubscriptionStore::class);
        $subscriptionStore->shouldReceive('open')->once()->andReturnUsing($fixture->subscriptionStore->open(...));
        $subscriptionStore->shouldReceive('heartbeat')->once()->andReturnUsing(static function (...$args) use ($fixture, &$tracked): void {
            $fixture->subscriptionStore->heartbeat(...$args);
            $tracked = true;
        });
        $subscriptionStore->shouldReceive('close')->once()->andReturnUsing($fixture->subscriptionStore->close(...));
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request(['promptsListChanged' => true])->getBody());
        $execution = (new McpSubscriptions($fixture->queryBus, $subscriptionStore, $fixture, NoticeSelection::create(['scope']), lifetimeSeconds: $stop === 'freshness' ? 20 : 1, prompts: true))->execution($request);
        $frames = [];
        $execution->run(static function ($frame) use (&$frames, $execution): void { $frames[] = $frame; $execution->cancel(); }, $fixture);

        self::assertTrue($tracked);
        self::assertSame([], $frames);
        self::assertSame(1, (int) $fixture->connection->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at > 0'));
        self::assertCount($stop === 'freshness' ? 1 : 0, $fixture->diagnostics);
        self::assertSame([], $fixture->visiblePrompt->calls);
        self::assertSame([], $fixture->hiddenPrompt->calls);
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
