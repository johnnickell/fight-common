<?php

declare(strict_types=1);

namespace Fight\Common\Application\Observability;

use Fight\Common\Domain\Observability\HealthResult;

/**
 * Interface HealthCheck
 */
interface HealthCheck
{
    /**
     * Executes the health check and returns a result
     */
    public function check(): HealthResult;

    /**
     * Retrieves the check name
     */
    public function name(): string;
}
