<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Observability\Metrics\NullMetricsCollector;
use Fight\Common\Adapter\ServiceContainer\Laravel\MetricsServiceProvider;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MetricsServiceProvider::class)]
final class MetricsServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_metrics_provider_uses_null_when_pulse_lacks_complete_metrics_port(): void
    {
        self::assertFalse(class_exists('Laravel\\Pulse\\Facades\\Pulse'));

        $application = new Application(__DIR__);
        $application->register(MetricsServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(MetricsCollector::class));
        self::assertFalse($application->bound(HttpClient::class));
        self::assertFalse($application->bound(ProcessRunner::class));
        self::assertTrue($application->bound('log'));
        self::assertFalse($application->resolved('log'));
        self::assertInstanceOf(NullMetricsCollector::class, $application->make(MetricsCollector::class));
    }
}
