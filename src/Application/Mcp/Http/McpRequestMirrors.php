<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Http;

use Fight\Common\Application\Mcp\McpRequestMirrors as ReleasedMcpRequestMirrors;

// Preserve both directions of the consumer request parameter contract.
$alias = __NAMESPACE__.'\\McpRequestMirrors';
interface_exists($alias, false) || class_alias(ReleasedMcpRequestMirrors::class, $alias);
