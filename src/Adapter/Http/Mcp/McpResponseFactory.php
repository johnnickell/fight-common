<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpJsonResponse;
use Fight\Common\Application\Mcp\McpProtocolError;
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
