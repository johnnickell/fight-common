<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Observability\Metrics;

use Fight\Common\Adapter\Observability\Metrics\StatsDMetricsCollector;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StatsDMetricsCollector::class)]
class StatsDMetricsCollectorTest extends UnitTestCase
{
    private array $captured = [];
    private StatsDMetricsCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $captured = &$this->captured;

        $this->collector = new StatsDMetricsCollector(
            'localhost',
            8125,
            'app',
            static function (string $metric) use (&$captured): void {
                $captured[] = $metric;
            }
        );
    }

    public function test_that_increment_formats_counter_with_tags(): void
    {
        $this->collector->increment('command.executed', ['type' => 'PlaceOrderCommand']);

        self::assertSame(
            ['app.command.executed:1|c|#type:PlaceOrderCommand'],
            $this->captured
        );
    }

    public function test_that_increment_without_tags_omits_tag_suffix(): void
    {
        $this->collector->increment('command.executed');

        self::assertSame(['app.command.executed:1|c'], $this->captured);
    }

    public function test_that_gauge_formats_gauge_metric(): void
    {
        $this->collector->gauge('queue.depth', 42.0, ['queue' => 'orders']);

        self::assertSame(
            ['app.queue.depth:42|g|#queue:orders'],
            $this->captured
        );
    }

    public function test_that_histogram_formats_timing_metric(): void
    {
        $this->collector->histogram('command.latency_ms', 123.5);

        self::assertSame(['app.command.latency_ms:123.5|ms'], $this->captured);
    }

    public function test_that_multiple_tags_are_joined_with_comma(): void
    {
        $this->collector->increment('event', ['type' => 'LoginCommand', 'exception' => 'AuthException']);

        self::assertStringContainsString('#type:LoginCommand,exception:AuthException', $this->captured[0]);
    }

    public function test_that_empty_prefix_omits_dot_separator(): void
    {
        $captured = [];
        $collector = new StatsDMetricsCollector(
            'localhost',
            8125,
            '',
            static function (string $metric) use (&$captured): void {
                $captured[] = $metric;
            }
        );

        $collector->increment('my.metric');

        self::assertSame(['my.metric:1|c'], $captured);
    }

    public function test_that_default_sender_uses_udp_socket(): void
    {
        // Exercises the default UDP sender path (no injected closure).
        // Sends to loopback; UDP is fire-and-forget so nothing listens.
        $collector = new StatsDMetricsCollector('127.0.0.1', 8125, 'test');
        $collector->increment('coverage.probe');
        $this->addToAssertionCount(1);
    }
}
