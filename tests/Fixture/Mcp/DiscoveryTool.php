<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Validation\Data\ApplicationData;
use LogicException;

final class DiscoveryTool implements McpTool
{
    #[McpToolInfo(
        name: 'alpha.find',
        description: 'Find public data',
        inputSchema: ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']],
        outputSchema: ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
    )]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        throw new LogicException('Discovery must never invoke a Tool.');
    }
}
