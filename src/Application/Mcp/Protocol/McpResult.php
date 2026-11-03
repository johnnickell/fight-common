<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol;

use Fight\Common\Application\Mcp\McpResult as ReleasedMcpResult;

// Preserve legacy capability return types, final factory identity and saved private result state.
$alias = __NAMESPACE__.'\\McpResult';
class_exists($alias, false) || class_alias(ReleasedMcpResult::class, $alias);
