<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\HttpKernel;

use RuntimeException;
use Fight\Common\Adapter\HttpFoundation\JSendResponse;
use Fight\Common\Adapter\HttpKernel\ErrorController;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(ErrorController::class)]
class ErrorControllerTest extends UnitTestCase
{
    public function test_that_handle_returns_fail_response_for_validation_exception(): void
    {
        $errors = ['email' => ['Email is required']];
        $exception = new ValidationException($errors);

        $controller = new ErrorController();
        $response = $controller->handle($exception);

        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertTrue($response->isFail());
        self::assertSame($errors, $response->getData()['data']);
        self::assertSame(400, $response->getStatusCode());
    }

    public function test_that_handle_returns_error_response_for_http_exception_with_status_code(): void
    {
        $exception = new NotFoundHttpException('Resource not found');

        $controller = new ErrorController();
        $response = $controller->handle($exception);

        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertTrue($response->isError());
        self::assertSame('error', $response->getData()['status']);
        self::assertSame('Resource not found', $response->getData()['message']);
        self::assertSame(404, $response->getStatusCode());
    }

    public function test_that_handle_returns_error_response_for_generic_exception_with_500(): void
    {
        $exception = new RuntimeException('Something broke');

        $controller = new ErrorController();
        $response = $controller->handle($exception);

        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertTrue($response->isError());
        self::assertSame('Something broke', $response->getData()['message']);
        self::assertSame(500, $response->getStatusCode());
    }
}
