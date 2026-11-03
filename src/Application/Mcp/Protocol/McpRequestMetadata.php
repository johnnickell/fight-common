<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol;

use Fight\Common\Application\Mcp\McpRequestMetadata as ReleasedMcpRequestMetadata;

// Keep immutable factory identity and tagged saved metadata readable without rehydration hooks.
$alias = __NAMESPACE__.'\\McpRequestMetadata';
class_exists($alias, false) || class_alias(ReleasedMcpRequestMetadata::class, $alias);
