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
     * @inheritDoc
     */
    public function increment(string $metric, array $tags = []): void
    {
    }

    /**
     * @inheritDoc
     */
    public function gauge(string $metric, float $value, array $tags = []): void
    {
    }

    /**
     * @inheritDoc
     */
    public function histogram(string $metric, float $value, array $tags = []): void
    {
    }
}
