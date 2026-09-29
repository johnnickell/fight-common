<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Resource;

/**
 * Interface McpResourceAvailability
 */
interface McpResourceAvailability
{
    /**
     * Returns the current consumer-owned visibility decision for neutral metadata
     *
     * Keep identity and permissions inside the consumer. The decision is evaluated afresh on every listing;
     * a URI, cursor or cache hint does not grant authority. Failure must not be converted to availability.
     */
    public function isAvailable(McpResourceInfo $resource): bool;
}
