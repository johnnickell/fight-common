<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Symfony;

use Fight\Common\Adapter\Http\Mcp\McpEventStream;
use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class McpResponseFactory
 *
 * Preserves progressive bodies for Symfony and Laravel without an event name or terminal sentinel
 */
final readonly class McpResponseFactory
{
    /**
     * Constructs McpResponseFactory
     */
    public function __construct(private McpResponseEmitter $emitter = new McpResponseEmitter())
    {
    }

    /**
     * Creates a native response without invoking or buffering a progressive Tool
     */
    public function fromResponse(ResponseInterface $response): Response
    {
        $this->emitter->prepare($response);
        if ($response->getBody() instanceof McpEventStream) {
            $native = new StreamedResponse(
                fn() => $this->emitter->emitBody($response),
                $response->getStatusCode(),
                $response->getHeaders()
            );
        } else {
            $native = new Response((string) $response->getBody(), $response->getStatusCode(), $response->getHeaders());
        }

        return $native->setProtocolVersion($response->getProtocolVersion());
    }
}
