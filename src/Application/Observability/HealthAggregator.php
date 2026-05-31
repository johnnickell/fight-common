<?php

declare(strict_types=1);

namespace Fight\Common\Application\Observability;

use Fight\Common\Domain\Observability\HealthReport;

/**
 * Interface HealthAggregator
 */
interface HealthAggregator
{
    /**
     * Registers a health check
     */
    public function addCheck(HealthCheck $check): void;

    /**
     * Runs all registered checks and returns an aggregated report
     */
    public function report(): HealthReport;
}
