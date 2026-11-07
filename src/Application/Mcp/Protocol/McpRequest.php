<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol;

use Fight\Common\Application\Mcp\McpRequest as ReleasedMcpRequest;

// Preserve legacy capability parameter types and private serialized property scopes.
$alias = __NAMESPACE__.'\\McpRequest';
class_exists($alias, false) || class_alias(ReleasedMcpRequest::class, $alias);
