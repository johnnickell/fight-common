<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Capability;

use Fight\Common\Application\Mcp\McpCapabilityRegistry as ReleasedMcpCapabilityRegistry;

// Retain the final released registry identity, constructor and reflection contract.
$alias = __NAMESPACE__.'\\McpCapabilityRegistry';
class_exists($alias, false) || class_alias(ReleasedMcpCapabilityRegistry::class, $alias);
