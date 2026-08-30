<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient;
use Fight\Common\Adapter\ServiceContainer\Laravel\HttpClientServiceProvider;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\ClientInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;

#[CoversClass(HttpClientServiceProvider::class)]
final class HttpClientServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_http_provider_uses_guzzle_when_laravel_lacks_psr_request_and_fight_promise_support(): void
    {
        $send = new ReflectionMethod(PendingRequest::class, 'send');

        self::assertSame('method', $send->getParameters()[0]->getName());
        self::assertFalse(method_exists(PendingRequest::class, 'sendAsync'));

        $application = new Application(__DIR__);
        $application->instance(ClientInterface::class, $this->mock(ClientInterface::class));
        $application->register(HttpClientServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(HttpClient::class));
        self::assertFalse($application->bound(ProcessRunner::class));
        self::assertFalse($application->bound(MetricsCollector::class));
        self::assertFalse($application->resolved('log'));
        self::assertInstanceOf(GuzzleClient::class, $application->make(HttpClient::class));
    }
}
