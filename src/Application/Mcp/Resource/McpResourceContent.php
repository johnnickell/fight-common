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
    private function __construct(
        private McpResourceInfo $resource,
        private StreamInterface $body,
        private bool $text,
        private ?string $digest = null
    ) {
        if ($digest !== null && preg_match('/\Asha256:[a-f0-9]{64}\z/D', $digest) !== 1) {
            throw new DomainException('Resource integrity requires a canonical SHA-256 digest.');
        }
    }

    /**
     * Creates a single-use text read whose original UTF-8 bytes must remain unchanged
     *
     * An optional canonical sha256: digest verifies consumed raw bytes before any successful result.
     */
    public static function text(McpResourceInfo $resource, StreamInterface $body, ?string $digest = null): self
    {
        return new self($resource, $body, true, $digest);
    }

    /**
     * Creates a single-use binary read to encode as Base64 without conversion
     *
     * An optional canonical sha256: digest covers raw bytes, never their Base64 representation.
     */
    public static function binary(McpResourceInfo $resource, StreamInterface $body, ?string $digest = null): self
    {
        return new self($resource, $body, false, $digest);
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
                || ($this->digest !== null && !hash_equals($this->digest, 'sha256:'.hash('sha256', $bytes)))
            ) {
                throw new DomainException('Resource content has inconsistent size, digest or invalid UTF-8 text.');
            }

            $item = array_intersect_key($metadata, array_flip(['uri', 'mimeType']));
            $item[$this->text ? 'text' : 'blob'] = $this->text ? $bytes : base64_encode($bytes);

            return $item;
        } finally {
            $this->body->close();
        }
    }
}
