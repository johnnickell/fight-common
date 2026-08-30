<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Laravel\Controller;

use Fight\Common\Adapter\Http\Laravel\Controller\ErrorController;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[CoversClass(ErrorController::class)]
final class ErrorControllerTest extends UnitTestCase
{
    public function test_that_handle_returns_laravel_jsend_fail_for_validation_errors(): void
    {
        $response = (new ErrorController())->handle(new ValidationException(['email' => ['Email is required']]));

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            '{"status":"fail","data":{"email":["Email is required"]}}',
            $response->getContent()
        );
    }

    public function test_that_handle_preserves_http_exception_statuses(): void
    {
        $response = (new ErrorController())->handle(new HttpException(429, 'Slow down'));

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('{"status":"error","message":"Slow down"}', $response->getContent());
    }

    public function test_that_handle_returns_an_internal_server_error_for_unclassified_exceptions(): void
    {
        $response = (new ErrorController())->handle(new RuntimeException('Unexpected failure'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"status":"error","message":"Unexpected failure"}', $response->getContent());
    }
}
