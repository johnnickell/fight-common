<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol;

use Fight\Common\Application\Mcp\McpRequestDecoder as ReleasedMcpRequestDecoder;

// Keep construction and decoded request identity compatible in either consumer direction.
$alias = __NAMESPACE__.'\\McpRequestDecoder';
class_exists($alias, false) || class_alias(ReleasedMcpRequestDecoder::class, $alias);
