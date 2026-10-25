<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Prompt;

/**
 * Interface McpPrompt
 */
interface McpPrompt
{
    /**
     * Returns immutable registration metadata without constructing Prompt content
     */
    public function info(): McpPromptInfo;

    /**
     * Returns complete public-safe content for one currently permitted selection
     *
     * Application-backed implementations use QueryBus; static instructions need no business Query.
     * Implementations are trusted consumer code, not sandboxed templates or an authorization policy.
     *
     * @param array<string, string> $arguments
     */
    public function get(array $arguments): McpPromptOutput;
}
