<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Middleware\Psr15;

use Fight\Common\Adapter\Middleware\Psr15\JsonRequestMiddleware;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(JsonRequestMiddleware::class)]
class JsonRequestMiddlewareTest extends UnitTestCase
{
    public function test_that_process_decodes_a_json_state_change_into_the_immutable_request(): void
    {
        $httpFactory = new HttpFactory();
        $request = $httpFactory->createServerRequest('PATCH', '/access')
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($httpFactory->createStream('{"role":"editor"}'));
        $response = $httpFactory->createResponse();
        $handler = $this->mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')
            ->once()
            ->withArgs(static function (ServerRequestInterface $handledRequest): bool {
                return $handledRequest->getParsedBody() === ['role' => 'editor'];
            })
            ->andReturn($response);

        self::assertSame($response, (new JsonRequestMiddleware())->process($request, $handler));
    }

    public function test_that_process_leaves_non_json_requests_unchanged(): void
    {
        $httpFactory = new HttpFactory();
        $request = $httpFactory->createServerRequest('GET', '/access')
            ->withBody($httpFactory->createStream('{"role":"editor"}'));
        $response = $httpFactory->createResponse();
        $handler = $this->mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with($request)->andReturn($response);

        self::assertSame($response, (new JsonRequestMiddleware())->process($request, $handler));
    }
}
