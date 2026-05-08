<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\HttpClient\Exception;

use Fight\Common\Application\HttpClient\Exception\HttpException;
use Fight\Common\Application\HttpClient\Exception\RequestException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(HttpException::class)]
class HttpExceptionTest extends UnitTestCase
{
    public function test_that_construction_sets_message_and_status_code_from_response(): void
    {
        $request = $this->mock(RequestInterface::class);
        $response = $this->mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(404);

        $exception = new HttpException('Not Found', $request, $response);

        self::assertSame('Not Found', $exception->getMessage());
        self::assertSame(404, $exception->getStatusCode());
    }

    public function test_that_get_status_code_returns_correct_status_code(): void
    {
        $request = $this->mock(RequestInterface::class);
        $response = $this->mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(500);

        $exception = new HttpException('Internal Server Error', $request, $response);

        self::assertSame(500, $exception->getStatusCode());
    }

    public function test_that_get_response_returns_the_response_passed_to_constructor(): void
    {
        $request = $this->mock(RequestInterface::class);
        $response = $this->mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(400);

        $exception = new HttpException('Bad Request', $request, $response);

        self::assertSame($response, $exception->getResponse());
    }

    public function test_that_create_builds_exception_with_normalized_message(): void
    {
        $request = $this->mock(RequestInterface::class);
        $request->shouldReceive('getRequestTarget')->andReturn('/api/resource');
        $request->shouldReceive('getMethod')->andReturn('POST');

        $response = $this->mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(422);
        $response->shouldReceive('getReasonPhrase')->andReturn('Unprocessable Entity');

        $exception = HttpException::create($request, $response);

        self::assertInstanceOf(HttpException::class, $exception);
        self::assertStringContainsString('/api/resource', $exception->getMessage());
        self::assertStringContainsString('POST', $exception->getMessage());
        self::assertStringContainsString('422', $exception->getMessage());
        self::assertStringContainsString('Unprocessable Entity', $exception->getMessage());
    }

    public function test_that_http_exception_extends_request_exception(): void
    {
        $request = $this->mock(RequestInterface::class);
        $response = $this->mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(403);

        $exception = new HttpException('Forbidden', $request, $response);

        self::assertInstanceOf(RequestException::class, $exception);
    }
}
