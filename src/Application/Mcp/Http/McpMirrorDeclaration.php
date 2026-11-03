<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Http;

use Fight\Common\Application\Mcp\McpMirrorDeclaration as ReleasedMcpMirrorDeclaration;

// Preserve immutable construction and saved mirror declarations without a second value type.
$alias = __NAMESPACE__.'\\McpMirrorDeclaration';
class_exists($alias, false) || class_alias(ReleasedMcpMirrorDeclaration::class, $alias);
