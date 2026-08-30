<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\ServiceContainer\Laravel\LoggingServiceProvider;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;

#[CoversClass(LoggingServiceProvider::class)]
final class LoggingServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_logging_provider_aliases_laravels_existing_psr3_logger_without_a_fight_wrapper(): void
    {
        $application = new Application(__DIR__);
        $logger = $this->mock(LoggerInterface::class);
        $application->instance('log', $logger);
        $application->register(LoggingServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(LoggerInterface::class));
        self::assertFalse($application->bound(HttpClient::class));
        self::assertFalse($application->bound(ProcessRunner::class));
        self::assertFalse($application->bound(MetricsCollector::class));
        self::assertSame($logger, $application->make(LoggerInterface::class));
    }
}
