<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

/**
 * Interface McpToolAvailability
 */
interface McpToolAvailability
{
    /**
     * Returns whether this Tool is available in the consumer-owned current request context
     *
     * Receives metadata only. Consumers privately retain their authentication and policy collaborators.
     * Discovery and later invocation must use the same decision; this is not a replacement for use-case authorization.
     */
    public function isAvailable(McpToolInfo $tool): bool;
}
