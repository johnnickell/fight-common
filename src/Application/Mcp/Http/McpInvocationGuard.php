<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Http;

use Fight\Common\Application\Mcp\McpInvocationGuard as ReleasedMcpInvocationGuard;

// Preserve consumer guard implementations in both old and canonical compositions.
$alias = __NAMESPACE__.'\\McpInvocationGuard';
interface_exists($alias, false) || class_alias(ReleasedMcpInvocationGuard::class, $alias);
