<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Middleware\Psr15;

use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpProjector;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;

/**
 * Class SafeJSendErrorMiddleware
 *
 * Explicitly composed safe error boundary. Leave the legacy middleware unchanged.
 */
final readonly class SafeJSendErrorMiddleware implements MiddlewareInterface
{
    private JSendResponseFactory $responseFactory;
    private SafeHttpErrorPolicy $httpErrorPolicy;
    private ?SafeHttpProjector $httpErrorProjector;

    /**
     * Constructs SafeJSendErrorMiddleware
     */
    public function __construct(
        JSendResponseFactory $responses,
        SafeHttpErrorPolicy $policy,
        private SafeHttpDiagnostics $diagnostics,
        ?SafeHttpProjector $projector = null
    ) {
        $this->responseFactory = $responses;
        $this->httpErrorPolicy = $policy;
        $this->httpErrorProjector = $projector;
    }

    /**
     * Handles downstream failures using the shared HTTP policy
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $throwable) {
            // Never re-enter the downstream handler or report a formatter failure recursively.
        }

        try {
            $this->diagnostics->report($throwable);
            $status = $this->httpErrorPolicy->status($throwable);
            $projection = $this->httpErrorProjector?->project($throwable, $status);
            [$status, $envelope, $headers] = $this->httpErrorPolicy->present($throwable, $projection);

            return $this->responseFactory->fromEnvelope($envelope, $status, $headers);
        } catch (Throwable) {
            // The original reporting attempt may have failed. No second log attempt is made.
        }

        try {
            [$status, $envelope, $headers] = $this->httpErrorPolicy->fallback();

            return $this->responseFactory->fromEnvelope($envelope, $status, $headers);
        } catch (Throwable) {
            // A broken PSR response/stream factory cannot be replaced by a valid PSR response.
            throw new RuntimeException('Unable to render safe HTTP error.');
        }
    }
}
