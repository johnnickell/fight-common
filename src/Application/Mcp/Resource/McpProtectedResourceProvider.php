<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Resource;

/**
 * Interface McpProtectedResourceProvider
 *
 * Optional provider-owned availability supplements, never replaces, the capability's consumer decision.
 * Discovery and find still expose all owned metadata internally for duplicate and budget validation.
 */
interface McpProtectedResourceProvider extends McpReadableResourceProvider, McpResourceAvailability
{
}
