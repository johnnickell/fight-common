<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Observability\Metrics;

use Fight\Common\Application\Observability\MetricsCollector;

/**
 * Class NullMetricsCollector
 */
final class NullMetricsCollector implements MetricsCollector
{
    /**
     * @param string $metric
     * @param array<string, string> $tags
     *
     * @inheritDoc
     */
    public function increment(string $metric, array $tags = []): void
    {
    }

    /**
     * @param string $metric
     * @param float $value
     * @param array<string, string> $tags
     *
     * @inheritDoc
     */
    public function gauge(string $metric, float $value, array $tags = []): void
    {
    }

    /**
     * @param string $metric
     * @param float $value
     * @param array<string, string> $tags
     *
     * @inheritDoc
     */
    public function histogram(string $metric, float $value, array $tags = []): void
    {
    }
}
