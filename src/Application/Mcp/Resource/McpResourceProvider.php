<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Resource;

/**
 * Interface McpResourceProvider
 */
interface McpResourceProvider
{
    /**
     * Returns fresh metadata in strictly increasing bytewise URI order without opening content
     *
     * Yield each URI once with stable metadata during this enumeration. Implementations may stream database
     * rows or an immutable catalog; they must not load or hash content to discover it. URI identity is exact,
     * not a normalized path or an instruction to fetch a URL. Hidden descriptors still have unique ownership.
     * This discovery contract deliberately has no content method; a later read port can coexist with it.
     *
     * @return iterable<McpResourceInfo>
     */
    public function resources(): iterable;
}
