<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol;

use Fight\Common\Application\Mcp\McpProtocolError as ReleasedMcpProtocolError;

// Keep safe error factories, constants and private serialized state on the released final type.
$alias = __NAMESPACE__.'\\McpProtocolError';
class_exists($alias, false) || class_alias(ReleasedMcpProtocolError::class, $alias);
