<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Detail\Data;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class ContentChunk
 */
final readonly class ContentChunk
{
    /**
     * Constructs ContentChunk
     */
    private function __construct(private string $bytes, private int $offset, private int $totalBytes)
    {
    }

    /**
     * Creates a bounded byte-exact Base64 chunk without splitting text into invalid JSON
     */
    public static function fromBody(string $body, int $offset, int $length): self
    {
        if (strlen($body) > 65536 || $offset < 0 || $offset > strlen($body) || $length < 1 || $length > 4096) {
            throw new DomainException('Invalid captured content boundary.');
        }

        return new self(substr($body, $offset, $length), $offset, strlen($body));
    }

    /**
     * Returns byte coordinates and continuation within this captured body only
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $end = $this->offset + strlen($this->bytes);

        return [
            'encoding'    => 'base64',
            'data'        => base64_encode($this->bytes),
            'offset'      => $this->offset,
            'bytes'       => strlen($this->bytes),
            'total_bytes' => $this->totalBytes,
            'next_offset' => $end < $this->totalBytes ? $end : null
        ];
    }
}
