<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpJsonResponse;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResponder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

/**
 * Class McpResponseFactory
 */
final readonly class McpResponseFactory
{
    /**
     * Constructs McpResponseFactory
     */
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private McpDiagnostics $diagnostics
    ) {
    }

    /**
     * Creates a direct JSON response with centralized protocol status mapping
     */
    public function fromResponse(McpJsonResponse $response): ResponseInterface
    {
        $status = match ($response->errorCode()) {
            null => 200,
            McpProtocolError::METHOD_NOT_FOUND => 404,
            McpProtocolError::INTERNAL_ERROR => 500,
            McpProtocolError::INVOCATION_LIMIT => 429,
            default => 400,
        };

        return $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streams->createStream($response->toJson()));
    }

    /**
     * Creates a lazy request-scoped SSE response after HTTP safeguards have passed
     */
    public function stream(McpRequest $request, McpResponder $responder): ResponseInterface
    {
        return $this->responses->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withHeader('X-Accel-Buffering', 'no')
            ->withoutHeader('Content-Length')
            ->withBody(new McpEventStream($request, $responder, $this->diagnostics));
    }

    /**
     * Creates an empty pre-protocol transport rejection
     */
    public function rejection(int $status): ResponseInterface
    {
        $response = $this->responses->createResponse($status)->withBody($this->streams->createStream());
        if ($status === 405) {
            return $response->withHeader('Allow', 'POST');
        }

        return $response;
    }

    /**
     * Records one unexpected failure and creates only a generic public error
     */
    public function failure(Throwable $failure, int|string|null $id): ResponseInterface
    {
        try {
            $this->diagnostics->record($failure);
        } catch (Throwable) {
            // A broken diagnostic sink must not expose its own failure to the client.
        }

        return $this->fromResponse(McpJsonResponse::error($id, McpProtocolError::internalError()));
    }
}
