<?php

declare(strict_types=1);

namespace Fight\Common\Application\Observability;

/**
 * Interface MetricsCollector
 */
interface MetricsCollector
{
    /**
     * Increments a counter metric
     */
    public function increment(string $metric, array $tags = []): void;

    /**
     * Sets a gauge metric to an absolute value
     */
    public function gauge(string $metric, float $value, array $tags = []): void;

    /**
     * Records a value in a histogram metric
     */
    public function histogram(string $metric, float $value, array $tags = []): void;
}
