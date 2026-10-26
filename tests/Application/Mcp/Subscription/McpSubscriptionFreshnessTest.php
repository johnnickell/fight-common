<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionExecution;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Notice\Data\NoticeRecoveryBatch;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Notice\Query\RecoverNotices;
use Fight\Test\Common\Fixture\Mcp\SapiDouble;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3).'/Fixture/Mcp/SapiDouble.php';

#[CoversClass(McpSubscriptionExecution::class)]
final class McpSubscriptionFreshnessTest extends UnitTestCase
{
    protected function setUp(): void { parent::setUp(); SapiDouble::reset(); }
    protected function tearDown(): void { SapiDouble::reset(); parent::tearDown(); }

    #[DataProvider('admissionBoundaries')]
    public function test_that_admission_returning_after_tracking_expiry_cannot_disclose_within_a_longer_lifetime(string $boundary, bool $emitter): void
    {
        $f = new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), lifetime: 20);
        $response = $f->endpoint->handle(SubscriptionFixture::request(id: '7'));
        $body = $response->getBody();
        if ($boundary === 'notice') {
            $ack = $body->read(65536);
            self::assertSame('7', json_decode(trim(substr($ack, 6)), true)['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
            (new NoticeFixture($f->db))->record(['id' => 'retained', 'type' => 'mcp.tools.list_changed']);
        }
        $calls = 0;
        $expired = null;
        $f->policy = static function ($filter, $uri, $notice) use ($f, $boundary, &$calls, &$expired): bool {
            if (($boundary === 'opening' && ++$calls === 2) || ($boundary === 'notice' && $notice !== null)) {
                if ($boundary === 'opening') { (new NoticeFixture($f->db))->record(['id' => 'retained', 'type' => 'mcp.tools.list_changed']); }
                $expired = $f->db->fetchAssociative('SELECT observed_at, expires_at FROM fight_mcp_subscriptions');
                // Real callback delay crosses the five-second lease, not the twenty-second stream lifetime.
                usleep(5100000);
                self::assertLessThanOrEqual(time(), (int) $expired['expires_at']);
            }
            return true;
        };
        $position = $body->tell();
        $start = hrtime(true);
        if ($emitter) {
            ob_start();
            try { new McpResponseEmitter()->emitBody($response); $wire = ob_get_contents(); }
            finally { ob_end_clean(); }
        } else {
            $wire = $body->read(65536);
        }
        self::assertLessThan(20000000000, hrtime(true) - $start);
        self::assertSame('', $wire, 'No new acknowledgment, change, keepalive or completion under expired tracking');
        self::assertSame($position, $body->tell());
        self::assertTrue($body->eof());
        self::assertNotNull($expired);
        $row = $f->db->fetchAssociative('SELECT observed_at, expires_at, closed_at FROM fight_mcp_subscriptions');
        self::assertSame((int) $expired['expires_at'], (int) $row['expires_at'], 'Expired ownership must not be renewed');
        self::assertTrue((int) $row['closed_at'] > 0 || (int) $row['expires_at'] <= time());
        self::assertNotEmpty($f->diagnostics);
        self::assertSame(1, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        $recovery = $f->queries->fetch(new RecoverNotices(NoticeSelection::create(['scope'])));
        self::assertInstanceOf(NoticeRecoveryBatch::class, $recovery);
        self::assertSame('complete', $recovery->status());
        self::assertSame(['retained'], array_map(static fn ($view) => $view->notice()->id(), $recovery->notices()));
        $body->close();
    }

    #[DataProvider('lostOwnership')]
    public function test_that_admission_cannot_emit_after_its_registration_is_closed_or_cleaned(string $boundary, bool $remove): void
    {
        $f = new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), lifetime: 20);
        $body = $f->endpoint->handle(SubscriptionFixture::request())->getBody();
        if ($boundary === 'notice') {
            $body->read(65536);
            (new NoticeFixture($f->db))->record(['id' => 'retained', 'type' => 'mcp.tools.list_changed']);
        }
        $calls = 0;
        $f->policy = static function ($filter, $uri, $notice) use ($f, $boundary, $remove, &$calls): bool {
            if (($boundary === 'opening' && ++$calls === 2) || ($boundary === 'notice' && $notice !== null)) {
                $f->db->executeStatement($remove ? 'DELETE FROM fight_mcp_subscriptions' : 'UPDATE fight_mcp_subscriptions SET closed_at = ?', $remove ? [] : [time()]);
            }
            return true;
        };
        self::assertSame('', $body->read(65536));
        self::assertTrue($body->eof());
        self::assertSame(0, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0'));
        self::assertSame($boundary === 'notice' ? 1 : 0, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        self::assertNotEmpty($f->diagnostics);
        $body->close();
    }

    #[DataProvider('storeBoundaries')]
    public function test_that_a_store_return_after_its_supplied_lease_expires_cannot_produce_a_new_frame(string $boundary): void
    {
        $f = new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), lifetime: 20);
        $hit = false;
        $stall = static function () use (&$hit): void {
            $hit = true;
            usleep(5100000);
        };
        $store = $this->mock(McpSubscriptionStore::class);
        $store->shouldReceive('open')->once()->andReturnUsing(static function (...$args) use ($f, $boundary, $stall): void {
            $f->store->open(...$args);
            if ($boundary === 'open') { $stall(); }
        });
        $beats = 0;
        $store->shouldReceive('heartbeat')->andReturnUsing(static function (...$args) use ($f, $boundary, $stall, &$beats): void {
            $f->store->heartbeat(...$args);
            ++$beats;
            if (($boundary === 'opening-heartbeat' && $beats === 1) || ($boundary === 'delivery-heartbeat' && $beats === 4) || ($boundary === 'keepalive-heartbeat' && $beats === 3)) { $stall(); }
        });
        $store->shouldReceive('close')->andReturnUsing(static function (...$args) use ($f, $boundary, $stall, &$hit): void {
            $f->store->close(...$args);
            if ($boundary === 'close' && !$hit) { $stall(); }
        });
        $request = new McpRequestDecoder()->decode((string) SubscriptionFixture::request()->getBody());
        $execution = (new McpSubscriptions($f->queries, $store, $f, NoticeSelection::create(['scope']), lifetimeSeconds: $boundary === 'close' ? 1 : 20, waitMicroseconds: 10000))->execution($request);
        $frames = [];
        $execution->run(static function ($frame) use (&$frames, $f, $boundary, $execution): void {
            $frames[] = $frame;
            if ($boundary === 'delivery-heartbeat' && count($frames) === 1) { (new NoticeFixture($f->db))->record(['id' => 'retained', 'type' => 'mcp.tools.list_changed']); }
            if ($boundary === 'close') { usleep(1100000); }
            // A defect must fail promptly rather than run for the whole long lifetime.
            if (count($frames) > 1) { $execution->cancel(); }
        }, $f);
        self::assertTrue($hit);
        self::assertCount(in_array($boundary, ['open', 'opening-heartbeat'], true) ? 0 : 1, $frames);
        if ($frames !== []) { self::assertStringContainsString('acknowledged', $frames[0]); }
        self::assertNotEmpty($f->diagnostics);
        self::assertGreaterThan(0, (int) $f->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        self::assertSame($boundary === 'delivery-heartbeat' ? 1 : 0, (int) $f->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
    }

    public static function lostOwnership(): iterable
    {
        foreach (['opening', 'notice'] as $boundary) {
            yield $boundary.'-closed' => [$boundary, false];
            yield $boundary.'-cleaned' => [$boundary, true];
        }
    }

    public static function storeBoundaries(): iterable
    {
        foreach (['open', 'opening-heartbeat', 'delivery-heartbeat', 'keepalive-heartbeat', 'close'] as $boundary) { yield [$boundary]; }
    }

    public static function admissionBoundaries(): iterable
    {
        yield 'opening-body' => ['opening', false];
        yield 'notice-body' => ['notice', false];
        yield 'opening-emitter' => ['opening', true];
        yield 'notice-emitter' => ['notice', true];
    }
}
