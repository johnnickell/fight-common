<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Resource;

use Fight\Common\Domain\Exception\DomainException;
use Psr\Http\Message\StreamInterface;

/**
 * Class McpResourceContent
 */
final readonly class McpResourceContent
{
    /**
     * Constructs McpResourceContent
     */
    private function __construct(private McpResourceInfo $resource, private StreamInterface $body, private bool $text)
    {
    }

    /**
     * Creates a single-use text read whose original UTF-8 bytes must remain unchanged
     */
    public static function text(McpResourceInfo $resource, StreamInterface $body): self
    {
        return new self($resource, $body, true);
    }

    /**
     * Creates a single-use binary read to encode as Base64 without conversion
     */
    public static function binary(McpResourceInfo $resource, StreamInterface $body): self
    {
        return new self($resource, $body, false);
    }

    /**
     * Returns one verified content item and closes its stream even when validation or reading fails
     *
     * This internal serving seam consumes at most the raw budget plus one overflow byte from a conforming
     * PSR stream. It never uses an unbounded getContents or string cast, seeks, or silently truncates content.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function consume(McpResourceInfo $expected, McpResourceReadLimits $limits): array
    {
        try {
            $limits->validate($expected);
            $metadata = $expected->toArray();
            $actual = $this->resource->toArray();
            if (
                $actual['uri'] !== $metadata['uri']
                || ($actual['mimeType'] ?? null) !== ($metadata['mimeType'] ?? null)
                || ($actual['size'] ?? null) !== ($metadata['size'] ?? null)
                || !$this->body->isReadable() || $this->body->tell() !== 0
            ) {
                throw new DomainException('Resource content must match its selected metadata and use a fresh stream.');
            }

            $bytes = '';
            do {
                $length = min(8192, $limits->maxContentBytes - strlen($bytes) + 1);
                $chunk = $this->body->read($length);
                if (strlen($chunk) > $length || strlen($bytes) + strlen($chunk) > $limits->maxContentBytes) {
                    throw new DomainException('Resource content exceeds its raw byte budget.');
                }

                if ($chunk === '' && !$this->body->eof()) {
                    throw new DomainException('Resource content reading made no progress.');
                }

                $bytes .= $chunk;
            } while (!$this->body->eof());

            if (
                (isset($metadata['size']) && $metadata['size'] !== strlen($bytes))
                || ($this->text && !mb_check_encoding($bytes, 'UTF-8'))
            ) {
                throw new DomainException('Resource content has inconsistent size or invalid UTF-8 text.');
            }

            $item = array_intersect_key($metadata, array_flip(['uri', 'mimeType']));
            $item[$this->text ? 'text' : 'blob'] = $this->text ? $bytes : base64_encode($bytes);

            return $item;
        } finally {
            $this->body->close();
        }
    }
}
