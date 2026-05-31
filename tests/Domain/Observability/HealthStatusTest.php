<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Observability;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Observability\HealthStatus;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HealthStatus::class)]
class HealthStatusTest extends UnitTestCase
{
    public function test_that_healthy_creates_healthy_status(): void
    {
        $status = HealthStatus::healthy();

        self::assertTrue($status->isHealthy());
        self::assertFalse($status->isDegraded());
        self::assertFalse($status->isUnhealthy());
        self::assertSame('healthy', $status->toString());
    }

    public function test_that_degraded_creates_degraded_status(): void
    {
        $status = HealthStatus::degraded();

        self::assertFalse($status->isHealthy());
        self::assertTrue($status->isDegraded());
        self::assertFalse($status->isUnhealthy());
        self::assertSame('degraded', $status->toString());
    }

    public function test_that_unhealthy_creates_unhealthy_status(): void
    {
        $status = HealthStatus::unhealthy();

        self::assertFalse($status->isHealthy());
        self::assertFalse($status->isDegraded());
        self::assertTrue($status->isUnhealthy());
        self::assertSame('unhealthy', $status->toString());
    }

    public function test_that_from_string_creates_valid_status(): void
    {
        self::assertSame('healthy', HealthStatus::fromString('healthy')->toString());
        self::assertSame('degraded', HealthStatus::fromString('degraded')->toString());
        self::assertSame('unhealthy', HealthStatus::fromString('unhealthy')->toString());
    }

    public function test_that_from_string_throws_for_invalid_value(): void
    {
        $this->expectException(DomainException::class);
        HealthStatus::fromString('unknown');
    }

    public function test_that_worst_returns_self_when_more_severe(): void
    {
        $unhealthy = HealthStatus::unhealthy();
        $healthy = HealthStatus::healthy();

        self::assertSame($unhealthy, $unhealthy->worst($healthy));
    }

    public function test_that_worst_returns_other_when_other_is_more_severe(): void
    {
        $healthy = HealthStatus::healthy();
        $unhealthy = HealthStatus::unhealthy();

        self::assertSame($unhealthy, $healthy->worst($unhealthy));
    }

    public function test_that_worst_returns_self_when_equal(): void
    {
        $a = HealthStatus::degraded();
        $b = HealthStatus::degraded();

        self::assertSame($a, $a->worst($b));
    }

    public function test_that_worst_escalates_from_degraded_to_unhealthy(): void
    {
        $degraded = HealthStatus::degraded();
        $unhealthy = HealthStatus::unhealthy();

        self::assertSame($unhealthy, $degraded->worst($unhealthy));
    }

    public function test_that_json_serialize_returns_string_value(): void
    {
        self::assertSame('healthy', HealthStatus::healthy()->jsonSerialize());
    }

    public function test_that_to_string_returns_string_value(): void
    {
        self::assertSame('degraded', (string) HealthStatus::degraded());
    }

    public function test_that_equals_returns_true_for_same_value(): void
    {
        self::assertTrue(HealthStatus::healthy()->equals(HealthStatus::healthy()));
    }

    public function test_that_equals_returns_false_for_different_value(): void
    {
        self::assertFalse(HealthStatus::healthy()->equals(HealthStatus::unhealthy()));
    }

    public function test_that_equals_returns_false_for_different_type(): void
    {
        self::assertFalse(HealthStatus::healthy()->equals('healthy'));
    }
}
