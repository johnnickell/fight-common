<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Capability;

use Fight\Common\Application\Mcp\McpCapability as ReleasedMcpCapability;

// Preserve both directions of consumer parameter/return compatibility with the released interface.
$alias = __NAMESPACE__.'\\McpCapability';
interface_exists($alias, false) || class_alias(ReleasedMcpCapability::class, $alias);
