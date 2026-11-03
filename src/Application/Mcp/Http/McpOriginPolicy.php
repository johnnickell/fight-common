<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Http;

use Fight\Common\Application\Mcp\McpOriginPolicy as ReleasedMcpOriginPolicy;

// Existing consumer policies must also satisfy canonical request-handler dependencies.
$alias = __NAMESPACE__.'\\McpOriginPolicy';
interface_exists($alias, false) || class_alias(ReleasedMcpOriginPolicy::class, $alias);
