<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Capability;

use Fight\Common\Application\Mcp\McpMetadataAwareCapability as ReleasedMcpMetadataAwareCapability;

// Keep the optional subtype identical for old and canonical capability implementations.
$alias = __NAMESPACE__.'\\McpMetadataAwareCapability';
interface_exists($alias, false) || class_alias(ReleasedMcpMetadataAwareCapability::class, $alias);
