<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Prompt;

/**
 * Interface McpPromptAvailability
 */
interface McpPromptAvailability
{
    /**
     * Returns the current consumer-owned decision without accepting authority from Prompt arguments
     */
    public function isAvailable(McpPromptInfo $prompt): bool;
}
