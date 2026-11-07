<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Resource;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpResourceLimits
 */
final readonly class McpResourceLimits
{
    /**
     * Constructs McpResourceLimits
     *
     * Sizes are encoded bytes, except maxUriBytes which bounds the exact URI string. The result budget covers
     * the complete result including central server identity, but not the outer JSON-RPC envelope.
     */
    public function __construct(
        /**
         * @deprecated Planned v1.5.0; use maxUriBytes() instead
         * Retained until a future major after at least one released minor
         */
        public int $maxUriBytes = 4096,
        /**
         * @deprecated Planned v1.5.0; use maxDescriptorBytes() instead
         * Retained until a future major after at least one released minor
         */
        public int $maxDescriptorBytes = 16384,
        /**
         * @deprecated Planned v1.5.0; use pageSize() instead
         * Retained until a future major after at least one released minor
         */
        public int $pageSize = 100,
        /**
         * @deprecated Planned v1.5.0; use maxDescriptors() instead
         * Retained until a future major after at least one released minor
         */
        public int $maxDescriptors = 10000,
        /**
         * @deprecated Planned v1.5.0; use maxProviders() instead
         * Retained until a future major after at least one released minor
         */
        public int $maxProviders = 32,
        /**
         * @deprecated Planned v1.5.0; use maxResultBytes() instead
         * Retained until a future major after at least one released minor
         */
        public int $maxResultBytes = 1048576
    ) {
        if (
            $maxUriBytes < 1 || $maxUriBytes > 1048576
            || $maxDescriptorBytes < $maxUriBytes + 32 || $maxDescriptorBytes > 16777216
            || $pageSize < 1 || $pageSize > 1000
            || $maxDescriptors < $pageSize || $maxDescriptors > 1000000
            || $maxProviders < 1 || $maxProviders > 256
            || $maxResultBytes < $maxDescriptorBytes + 256 || $maxResultBytes > 67108864
        ) {
            throw new DomainException('Resource discovery limits must be finite, positive and mutually consistent.');
        }
    }

    /**
     * Returns the exact URI byte budget
     */
    public function maxUriBytes(): int
    {
        return $this->maxUriBytes;
    }

    /**
     * Returns the encoded descriptor byte budget
     */
    public function maxDescriptorBytes(): int
    {
        return $this->maxDescriptorBytes;
    }

    /**
     * Returns the maximum descriptors per page
     */
    public function pageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Returns the maximum catalog descriptor count
     */
    public function maxDescriptors(): int
    {
        return $this->maxDescriptors;
    }

    /**
     * Returns the maximum provider count
     */
    public function maxProviders(): int
    {
        return $this->maxProviders;
    }

    /**
     * Returns the complete encoded result byte budget
     */
    public function maxResultBytes(): int
    {
        return $this->maxResultBytes;
    }
}
