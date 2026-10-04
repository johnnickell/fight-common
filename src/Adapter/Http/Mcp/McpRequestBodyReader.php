<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fight\Common\Application\Mcp\McpRequestLimits;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * Class McpRequestBodyReader
 *
 * @internal
 */
final class McpRequestBodyReader
{
    /**
     * Reads the body within its byte budget or returns null after one excess byte
     *
     * Seekable bodies restart as they did with PSR string conversion. Non-seekable bodies start at their
     * current position. The caller retains stream ownership; declared lengths never replace actual reads.
     */
    public function read(StreamInterface $body, McpRequestLimits $limits): ?string
    {
        try {
            if ($body->isSeekable()) {
                $body->rewind();
            }

            $json = '';
            $remaining = $limits->maxBytes;
            do {
                $length = min(8192, $remaining + 1);
                $chunk = $body->read($length);
                $bytes = strlen($chunk);
                if ($bytes > $length) {
                    throw new RuntimeException('The MCP request stream exceeded the requested read size.');
                }

                if ($bytes === 0) {
                    if (!$body->eof()) {
                        throw new RuntimeException('The MCP request stream made no progress.');
                    }

                    break;
                }

                if ($bytes > $remaining) {
                    return null;
                }

                $json .= $chunk;
                $remaining -= $bytes;
            } while (!$body->eof());

            return $json;
        } catch (Throwable $throwable) {
            // Stream collaborators cannot inject protocol errors or an unvalidated request identifier.
            throw new RuntimeException('Unable to read the MCP request body.', 0, $throwable);
        }
    }
}
