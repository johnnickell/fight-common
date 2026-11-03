<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol;

use Fight\Common\Application\Mcp\McpRequestLimits as ReleasedMcpRequestLimits;

// Preserve the released readonly fields, named arguments and native saved configuration.
$alias = __NAMESPACE__.'\\McpRequestLimits';
class_exists($alias, false) || class_alias(ReleasedMcpRequestLimits::class, $alias);
