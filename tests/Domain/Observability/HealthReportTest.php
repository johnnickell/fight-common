<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Observability;

use DateTimeImmutable;
use Fight\Common\Domain\Observability\HealthReport;
use Fight\Common\Domain\Observability\HealthResult;
use Fight\Common\Domain\Observability\HealthStatus;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HealthReport::class)]
class HealthReportTest extends UnitTestCase
{
    public function test_that_from_results_with_empty_array_is_healthy(): void
    {
        $report = HealthReport::fromResults([]);

        self::assertTrue($report->isHealthy());
        self::assertSame('healthy', $report->overall()->toString());
        self::assertSame([], $report->results());
    }

    public function test_that_from_results_computes_worst_status(): void
    {
        $results = [
            new HealthResult('db', HealthStatus::healthy(), 'ok'),
            new HealthResult('queue', HealthStatus::degraded(), 'backed up'),
            new HealthResult('api', HealthStatus::unhealthy(), 'down'),
        ];

        $report = HealthReport::fromResults($results);

        self::assertTrue($report->overall()->isUnhealthy());
        self::assertFalse($report->isHealthy());
        self::assertCount(3, $report->results());
    }

    public function test_that_from_results_with_only_healthy_checks_is_healthy(): void
    {
        $results = [
            new HealthResult('db', HealthStatus::healthy()),
            new HealthResult('cache', HealthStatus::healthy()),
        ];

        $report = HealthReport::fromResults($results);

        self::assertTrue($report->overall()->isHealthy());
    }

    public function test_that_from_results_with_degraded_check_is_degraded(): void
    {
        $results = [
            new HealthResult('db', HealthStatus::healthy()),
            new HealthResult('queue', HealthStatus::degraded()),
        ];

        $report = HealthReport::fromResults($results);

        self::assertTrue($report->overall()->isDegraded());
    }

    public function test_that_constructor_sets_all_fields(): void
    {
        $overall = HealthStatus::healthy();
        $results = [new HealthResult('db', HealthStatus::healthy())];
        $timestamp = new DateTimeImmutable('2026-01-01T00:00:00Z');

        $report = new HealthReport($overall, $results, $timestamp);

        self::assertSame($overall, $report->overall());
        self::assertSame($results, $report->results());
        self::assertSame($timestamp, $report->timestamp());
    }

    public function test_that_to_array_contains_status_timestamp_and_checks(): void
    {
        $report = HealthReport::fromResults([
            new HealthResult('db', HealthStatus::healthy(), 'ping 2ms'),
        ]);

        $array = $report->toArray();

        self::assertSame('healthy', $array['status']);
        self::assertArrayHasKey('timestamp', $array);
        self::assertCount(1, $array['checks']);
        self::assertSame('db', $array['checks'][0]['name']);
    }

    public function test_that_json_serialize_matches_to_array(): void
    {
        $report = HealthReport::fromResults([]);

        self::assertSame($report->toArray(), $report->jsonSerialize());
    }
}
