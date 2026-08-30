<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Observability\Metrics\NullMetricsCollector;
use Fight\Common\Application\Observability\MetricsCollector;
use Illuminate\Support\ServiceProvider;

/**
 * Class MetricsServiceProvider
 *
 * Registers the shared no-op metrics fallback.
 *
 * Pulse is not a complete increment, gauge, and histogram provider for the
 * Fight port, and its application telemetry remains application-owned.
 */
final class MetricsServiceProvider extends ServiceProvider
{
    /**
     * Registers the metrics capability
     */
    public function register(): void
    {
        $this->app->singleton(MetricsCollector::class, NullMetricsCollector::class);
    }
}
