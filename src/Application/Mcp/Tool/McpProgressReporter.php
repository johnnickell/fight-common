<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpProgressReporter as ReleasedMcpProgressReporter;

// A legacy Tool's narrower parameter spelling must accept canonical reporters without adaptation.
$alias = __NAMESPACE__.'\\McpProgressReporter';
interface_exists($alias, false) || class_alias(ReleasedMcpProgressReporter::class, $alias);
