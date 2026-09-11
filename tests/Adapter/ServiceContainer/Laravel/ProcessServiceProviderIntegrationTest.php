<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner;
use Fight\Common\Adapter\ServiceContainer\Laravel\ProcessServiceProvider;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Foundation\Application;
use Illuminate\Process\PendingProcess;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;

#[CoversClass(ProcessServiceProvider::class)]
final class ProcessServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_process_provider_uses_symfony_when_laravel_lacks_attach_clear_and_retry_lifecycle(): void
    {
        self::assertFalse(method_exists(PendingProcess::class, 'attach'));
        self::assertFalse(method_exists(PendingProcess::class, 'clear'));
        self::assertFalse(method_exists(PendingProcess::class, 'retry'));
        $run = new ReflectionMethod(PendingProcess::class, 'run');

        self::assertSame('command', $run->getParameters()[0]->getName());

        $application = new Application(__DIR__);
        $application->register(ProcessServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(ProcessRunner::class));
        self::assertFalse($application->bound(HttpClient::class));
        self::assertFalse($application->bound(MetricsCollector::class));
        self::assertTrue($application->bound('log'));
        self::assertFalse($application->resolved('log'));
        self::assertInstanceOf(SymfonyProcessRunner::class, $application->make(ProcessRunner::class));
    }
}
