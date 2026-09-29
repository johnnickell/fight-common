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
        public int $maxUriBytes = 4096,
        public int $maxDescriptorBytes = 16384,
        public int $pageSize = 100,
        public int $maxDescriptors = 10000,
        public int $maxProviders = 32,
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
}
