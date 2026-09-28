<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Class McpResponseEmitter
 *
 * Emits through PHP's SAPI with buffering disabled by the consumer runtime
 */
final readonly class McpResponseEmitter
{
    /**
     * Sends one PSR response through the PHP SAPI without owning a route or request lifecycle
     */
    public function emit(ResponseInterface $response): void
    {
        if (headers_sent()) {
            throw new RuntimeException('MCP response headers have already been sent.');
        }

        $this->prepare($response);
        header(sprintf(
            'HTTP/%s %d %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        ));
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name.': '.$value, false);
            }
        }

        $this->emitBody($response);
    }

    /**
     * Validates the streaming runtime before a native response sends its headers
     */
    public function prepare(ResponseInterface $response): void
    {
        if (!$response->getBody() instanceof McpEventStream) {
            return;
        }

        if (
            ob_get_level() !== 0
            || !in_array(strtolower((string) ini_get('zlib.output_compression')), ['', '0', 'off'], true)
        ) {
            throw new RuntimeException('Live MCP emission requires output buffering and compression disabled.');
        }

        if ($response->hasHeader('Content-Length') || $response->hasHeader('Content-Encoding')) {
            throw new RuntimeException('Live MCP emission cannot use a retained length or content encoding.');
        }
    }

    /**
     * Sends a body after native or PSR headers and propagates observed closure before resuming Tool work
     */
    public function emitBody(ResponseInterface $response): void
    {
        $this->prepare($response);
        $body = $response->getBody();
        if (!$body instanceof McpEventStream) {
            echo (string) $body;

            return;
        }

        // PHP must not kill execution at the write that observes closure: the Tool owns its safe stopping point.
        $previous = ignore_user_abort(true);
        try {
            while (!$body->eof() && connection_aborted() === 0) {
                echo $body->read(8192);
                flush();
            }
        } finally {
            try {
                $body->close();
            } finally {
                ignore_user_abort((bool) $previous);
            }
        }
    }
}
