<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Middleware\Psr15;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Application\HttpFoundation\HttpStatus;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Common\Domain\Type\Arrayable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Class JSendErrorMiddleware
 *
 * Converts downstream failures into provider-neutral JSend error responses.
 */
final readonly class JSendErrorMiddleware implements MiddlewareInterface
{
    /**
     * Constructs JSendErrorMiddleware
     */
    public function __construct(private JSendResponseFactory $responseFactory)
    {
    }

    /**
     * Converts downstream exceptions into JSend responses
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $exception) {
            return $this->responseFactory->fromEnvelope(
                JSendEnvelope::fail($this->presentation($exception->getErrors())),
                HttpStatus::BAD_REQUEST
            );
        } catch (Throwable $exception) {
            return $this->responseFactory->fromEnvelope(
                JSendEnvelope::error($exception->getMessage()),
                HttpStatus::INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Creates typed presentation data from validation errors
     *
     * @param array<string, mixed> $errors
     */
    private function presentation(array $errors): Arrayable
    {
        return new readonly class ($errors) implements Arrayable {
            /**
             * @param array<string, mixed> $errors
             */
            public function __construct(private array $errors)
            {
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return $this->errors;
            }
        };
    }
}
