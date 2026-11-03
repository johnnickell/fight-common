<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol;

use Fight\Common\Application\Mcp\McpServerInfo as ReleasedMcpServerInfo;

// Preserve released named construction, final identity and saved server information.
$alias = __NAMESPACE__.'\\McpServerInfo';
class_exists($alias, false) || class_alias(ReleasedMcpServerInfo::class, $alias);
