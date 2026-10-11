<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpFailure;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\SafeJSendErrorMiddleware;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;

#[CoversNothing]
final class SafePsr15ErrorJourneyTest extends TestCase
{
    public function test_that_psr_request_journey_keeps_secrets_out_of_status_headers_and_body(): void
    {
        $http = new HttpFactory();
        $diagnostics = new class implements SafeHttpDiagnostics {
            /** @var list<string> */
            public array $reported = [];

            public function report(Throwable $failure): void
            {
                $this->reported[] = $failure::class;
            }
        };
        $middleware = new SafeJSendErrorMiddleware(
            new JSendResponseFactory($http, $http),
            new SafeHttpErrorPolicy(),
            $diagnostics
        );
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                if ($request->getUri()->getPath() === '/method') {
                    throw SafeHttpFailure::methodNotAllowed('GET, POST', 'password=synthetic-secret');
                }
                if ($request->getUri()->getPath() === '/limit') {
                    throw new RateLimitExceeded('password=synthetic-secret', 503, new RuntimeException('private cause'), 0);
                }

                throw new RuntimeException('password=synthetic-secret', 404, new RuntimeException('private cause'));
            }
        };
        $method = $middleware->process($http->createServerRequest('PUT', '/method'), $handler);
        self::assertSame(405, $method->getStatusCode());
        self::assertSame('GET, POST', $method->getHeaderLine('Allow'));
        self::assertSame('application/json', $method->getHeaderLine('Content-Type'));
        self::assertSame(['status' => 'fail', 'data' => ['message' => 'Method not allowed.']], json_decode((string) $method->getBody(), true));

        $limit = $middleware->process($http->createServerRequest('POST', '/limit'), $handler);
        self::assertSame(429, $limit->getStatusCode());
        self::assertFalse($limit->hasHeader('Retry-After'));
        self::assertStringNotContainsString('synthetic-secret', (string) $limit->getBody());

        $unknown = $middleware->process($http->createServerRequest('GET', '/missing'), $handler);
        self::assertSame(500, $unknown->getStatusCode());
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], json_decode((string) $unknown->getBody(), true));
        self::assertSame([SafeHttpFailure::class, RateLimitExceeded::class, RuntimeException::class], $diagnostics->reported);
    }
}
