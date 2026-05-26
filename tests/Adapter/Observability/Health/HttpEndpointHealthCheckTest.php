<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Observability\Health;

use Fight\Common\Adapter\Observability\Health\HttpEndpointHealthCheck;
use Fight\Common\Application\HttpClient\Message\MessageFactory;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

#[CoversClass(HttpEndpointHealthCheck::class)]
class HttpEndpointHealthCheckTest extends UnitTestCase
{
    public function test_that_check_returns_healthy_for_2xx_response(): void
    {
        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);

        /** @var MockInterface|ResponseInterface $response */
        $response = $this->mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        /** @var MockInterface|MessageFactory $factory */
        $factory = $this->mock(MessageFactory::class);
        $factory->shouldReceive('createRequest')->with('GET', 'https://example.com/health')->andReturn($request);

        /** @var MockInterface|HttpClient $client */
        $client = $this->mock(HttpClient::class);
        $client->shouldReceive('send')->with($request)->andReturn($response);

        $check = new HttpEndpointHealthCheck($client, $factory, 'https://example.com/health');
        $result = $check->check();

        self::assertTrue($result->status()->isHealthy());
        self::assertStringContainsString('200', $result->message() ?? '');
    }

    public function test_that_check_returns_unhealthy_for_5xx_response(): void
    {
        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);

        /** @var MockInterface|ResponseInterface $response */
        $response = $this->mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(503);

        /** @var MockInterface|MessageFactory $factory */
        $factory = $this->mock(MessageFactory::class);
        $factory->shouldReceive('createRequest')->andReturn($request);

        /** @var MockInterface|HttpClient $client */
        $client = $this->mock(HttpClient::class);
        $client->shouldReceive('send')->andReturn($response);

        $check = new HttpEndpointHealthCheck($client, $factory, 'https://example.com/health');
        $result = $check->check();

        self::assertTrue($result->status()->isUnhealthy());
        self::assertSame('HTTP 503', $result->message());
    }

    public function test_that_check_returns_unhealthy_when_http_throws(): void
    {
        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);

        /** @var MockInterface|MessageFactory $factory */
        $factory = $this->mock(MessageFactory::class);
        $factory->shouldReceive('createRequest')->andReturn($request);

        /** @var MockInterface|HttpClient $client */
        $client = $this->mock(HttpClient::class);
        $client->shouldReceive('send')->andThrow(new RuntimeException('connection refused'));

        $check = new HttpEndpointHealthCheck($client, $factory, 'https://example.com/health');
        $result = $check->check();

        self::assertTrue($result->status()->isUnhealthy());
        self::assertSame('connection refused', $result->message());
    }

    public function test_that_name_method_returns_check_name(): void
    {
        /** @var MockInterface|HttpClient $client */
        $client = $this->mock(HttpClient::class);

        /** @var MockInterface|MessageFactory $factory */
        $factory = $this->mock(MessageFactory::class);

        $check = new HttpEndpointHealthCheck($client, $factory, 'https://pay.example.com', 'payment-api');

        self::assertSame('payment-api', $check->name());
    }
}
