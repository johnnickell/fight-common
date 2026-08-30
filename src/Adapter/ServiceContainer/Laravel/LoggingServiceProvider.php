<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Class LoggingServiceProvider
 *
 * Wires Laravel's existing PSR-3 logger without a Fight wrapper.
 */
final class LoggingServiceProvider extends ServiceProvider
{
    /**
     * Registers the logging capability
     */
    public function register(): void
    {
        $this->app->alias('log', LoggerInterface::class);
    }
}
