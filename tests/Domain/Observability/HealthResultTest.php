<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Observability;

use Fight\Common\Domain\Observability\HealthResult;
use Fight\Common\Domain\Observability\HealthStatus;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HealthResult::class)]
class HealthResultTest extends UnitTestCase
{
    public function test_that_constructor_sets_fields(): void
    {
        $result = new HealthResult('database', HealthStatus::healthy(), 'ping 3ms', ['host' => 'db']);

        self::assertSame('database', $result->name());
        self::assertTrue($result->status()->isHealthy());
        self::assertSame('ping 3ms', $result->message());
        self::assertSame(['host' => 'db'], $result->context());
    }

    public function test_that_message_defaults_to_null(): void
    {
        $result = new HealthResult('queue', HealthStatus::degraded());

        self::assertNull($result->message());
        self::assertSame([], $result->context());
    }

    public function test_that_to_array_includes_all_fields(): void
    {
        $result = new HealthResult('payment', HealthStatus::unhealthy(), 'connection refused');

        $array = $result->toArray();

        self::assertSame('payment', $array['name']);
        self::assertSame('unhealthy', $array['status']);
        self::assertSame('connection refused', $array['message']);
        self::assertSame([], $array['context']);
    }

    public function test_that_json_serialize_matches_to_array(): void
    {
        $result = new HealthResult('db', HealthStatus::healthy());

        self::assertSame($result->toArray(), $result->jsonSerialize());
    }
}
