<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol\Exception;

use Fight\Common\Application\Mcp\McpProtocolException as ReleasedMcpProtocolException;

// Preserve catchability in both directions and the released Throwable identity and state.
$alias = __NAMESPACE__.'\\McpProtocolException';
class_exists($alias, false) || class_alias(ReleasedMcpProtocolException::class, $alias);
