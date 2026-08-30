<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Laravel\Controller;

use Fight\Common\Adapter\Http\Laravel\JSendResponse;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Common\Domain\Type\Arrayable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Class ErrorController
 */
final class ErrorController
{
    /**
     * Handles an exception
     */
    public function handle(Throwable $exception): JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return JSendResponse::fail($this->presentation($exception->getErrors()));
        }

        if ($exception instanceof HttpExceptionInterface) {
            return JSendResponse::error($exception->getMessage(), $exception->getStatusCode());
        }

        return JSendResponse::error($exception->getMessage());
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
