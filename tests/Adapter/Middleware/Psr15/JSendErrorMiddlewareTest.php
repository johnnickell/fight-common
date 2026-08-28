<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Middleware\Psr15;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\JSendErrorMiddleware;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[CoversClass(JSendErrorMiddleware::class)]
class JSendErrorMiddlewareTest extends UnitTestCase
{
    public function test_that_process_returns_a_fail_envelope_for_validation_failures(): void
    {
        $httpFactory = new HttpFactory();
        $handler = $this->throwingHandler(new ValidationException(['email' => ['Email is required']]));

        $response = $this->middleware($httpFactory)->process($httpFactory->createServerRequest('POST', '/access'), $handler);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('{"status":"fail","data":{"email":["Email is required"]}}', (string) $response->getBody());
    }

    public function test_that_process_returns_an_error_envelope_for_unexpected_failures(): void
    {
        $httpFactory = new HttpFactory();
        $handler = $this->throwingHandler(new RuntimeException('The bridge is out'));

        $response = $this->middleware($httpFactory)->process($httpFactory->createServerRequest('GET', '/access'), $handler);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"status":"error","message":"The bridge is out"}', (string) $response->getBody());
    }

    private function middleware(HttpFactory $httpFactory): JSendErrorMiddleware
    {
        return new JSendErrorMiddleware(new JSendResponseFactory($httpFactory, $httpFactory));
    }

    private function throwingHandler(\Throwable $exception): RequestHandlerInterface
    {
        $handler = $this->mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andThrow($exception);

        return $handler;
    }
}
