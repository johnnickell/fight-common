<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Prompt;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpPromptLimits
 */
final readonly class McpPromptLimits
{
    /**
     * Constructs McpPromptLimits
     *
     * Catalog bytes sum complete encoded definitions, including concealed entries. Result bytes include
     * central metadata; response bytes additionally include the actual JSON-RPC envelope and request ID.
     * An entry or atomic page too large for either serving budget fails instead of truncating or looping.
     */
    public function __construct(
        public int $maxPrompts = 1000,
        public int $maxCatalogBytes = 1048576,
        public int $pageSize = 100,
        public int $maxResultBytes = 1048576,
        public int $maxResponseBytes = 1048576
    ) {
        if (
            $maxPrompts < 1 || $maxPrompts > 10000
            || $maxCatalogBytes < 1 || $maxCatalogBytes > 16777216
            || $pageSize < 1 || $pageSize > 1000
            || $maxResultBytes < 1 || $maxResultBytes > 16777216
            || $maxResponseBytes < 1 || $maxResponseBytes > 16777216
        ) {
            throw new DomainException('Prompt limits must be finite and positive within supported bounds.');
        }
    }
}
