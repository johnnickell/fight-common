<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpKernel;

use Fight\Common\Adapter\HttpFoundation\JSendResponse;
use Fight\Common\Domain\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Class ErrorController
 */
class ErrorController
{
    /**
     * Handles an exception
     */
    public function handle(Throwable $exception): JSendResponse
    {
        if ($exception instanceof ValidationException) {
            return JSendResponse::fail($exception->getErrors());
        }

        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        return JSendResponse::error($exception->getMessage(), $statusCode);
    }
}
