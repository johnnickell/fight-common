<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Messaging;

use Fight\Common\Application\Mcp\Tool\McpToolInfo;

/**
 * Interface McpToolAuditMetadata
 */
interface McpToolAuditMetadata
{
    /**
     * Returns explicitly projected non-secret audit fields from consumer-owned context
     *
     * Consumers must never return credentials, raw arguments, headers or authorization decisions.
     * Common validates shape and declared field names, not the meaning of arbitrary scalar values.
     *
     * @return array<string, mixed>
     */
    public function fieldsFor(McpToolInfo $tool): array;
}
