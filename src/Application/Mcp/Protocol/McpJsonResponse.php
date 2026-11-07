<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol;

use Fight\Common\Application\Mcp\McpJsonResponse as ReleasedMcpJsonResponse;

// Preserve final factory identity and native saved response state as well as array/JSON output.
$alias = __NAMESPACE__.'\\McpJsonResponse';
class_exists($alias, false) || class_alias(ReleasedMcpJsonResponse::class, $alias);
