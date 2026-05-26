<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Observability\Health;

use Fight\Common\Application\Observability\HealthAggregator;
use Fight\Common\Application\Observability\HealthCheck;
use Fight\Common\Domain\Observability\HealthReport;

/**
 * Class HealthReporter
 */
final class HealthReporter implements HealthAggregator
{
    /** @var HealthCheck[] */
    private array $checks = [];

    /**
     * @inheritDoc
     */
    public function addCheck(HealthCheck $check): void
    {
        $this->checks[] = $check;
    }

    /**
     * @inheritDoc
     */
    public function report(): HealthReport
    {
        $results = [];

        foreach ($this->checks as $check) {
            $results[] = $check->check();
        }

        return HealthReport::fromResults($results);
    }
}
