<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpRequestLimits
 */
final readonly class McpRequestLimits
{
    /**
     * Constructs McpRequestLimits
     *
     * The byte budget covers the complete encoded request. Depth uses PHP JSON decoder semantics:
     * at most maxDepth minus one nested containers, including empty containers and the root object.
     */
    public function __construct(
        /**
         * @deprecated Use maxBytes() instead; retained until a future major after at least one released minor
         */
        public int $maxBytes = 1048576,
        /**
         * @deprecated Use maxDepth() instead; retained until a future major after at least one released minor
         */
        public int $maxDepth = 512
    ) {
        if ($maxBytes < 1 || $maxBytes > 67108864 || $maxDepth < 1 || $maxDepth > 512) {
            throw new DomainException('MCP request limits require 1–67108864 bytes and a JSON depth of 1–512.');
        }
    }

    /**
     * Returns the complete encoded request byte budget
     */
    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    /**
     * Returns the PHP JSON decoding depth limit
     */
    public function maxDepth(): int
    {
        return $this->maxDepth;
    }
}
