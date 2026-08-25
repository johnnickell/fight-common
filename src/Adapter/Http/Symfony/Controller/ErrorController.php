<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Symfony\Controller;

use Fight\Common\Adapter\Http\Symfony\JSendResponse;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Common\Domain\Type\Arrayable;
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
            return JSendResponse::fail($this->presentation($exception->getErrors()));
        }

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
        } else {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        return JSendResponse::error($exception->getMessage(), $statusCode);
    }

    /**
     * Creates typed presentation data from validation errors
     *
     * @param array<string, mixed> $errors
     */
    private function presentation(array $errors): Arrayable
    {
        return new readonly class ($errors) implements Arrayable {
            /** @param array<string, mixed> $errors */
            public function __construct(private array $errors)
            {
            }

            /** @return array<string, mixed> */
            public function toArray(): array
            {
                return $this->errors;
            }
        };
    }
}
