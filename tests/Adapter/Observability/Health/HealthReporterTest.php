<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Observability\Health;

use Fight\Common\Adapter\Observability\Health\HealthReporter;
use Fight\Common\Application\Observability\HealthCheck;
use Fight\Common\Domain\Observability\HealthResult;
use Fight\Common\Domain\Observability\HealthStatus;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HealthReporter::class)]
class HealthReporterTest extends UnitTestCase
{
    public function test_that_report_with_no_checks_is_healthy(): void
    {
        $reporter = new HealthReporter();
        $report = $reporter->report();

        self::assertTrue($report->isHealthy());
        self::assertSame([], $report->results());
    }

    public function test_that_report_aggregates_registered_checks(): void
    {
        $healthyCheck = new class implements HealthCheck {
            public function name(): string { return 'db'; }
            public function check(): HealthResult { return new HealthResult('db', HealthStatus::healthy()); }
        };

        $degradedCheck = new class implements HealthCheck {
            public function name(): string { return 'queue'; }
            public function check(): HealthResult { return new HealthResult('queue', HealthStatus::degraded()); }
        };

        $reporter = new HealthReporter();
        $reporter->addCheck($healthyCheck);
        $reporter->addCheck($degradedCheck);

        $report = $reporter->report();

        self::assertTrue($report->overall()->isDegraded());
        self::assertCount(2, $report->results());
    }

    public function test_that_report_reflects_unhealthy_check(): void
    {
        $unhealthyCheck = new class implements HealthCheck {
            public function name(): string { return 'api'; }
            public function check(): HealthResult { return new HealthResult('api', HealthStatus::unhealthy(), 'down'); }
        };

        $reporter = new HealthReporter();
        $reporter->addCheck($unhealthyCheck);

        $report = $reporter->report();

        self::assertTrue($report->overall()->isUnhealthy());
    }
}
