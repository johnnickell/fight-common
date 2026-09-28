<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Validation\Data\ApplicationData;

final class MirroredTool implements McpTool
{
    public int $calls = 0;

    #[McpToolInfo('mirrored', 'Read a mirrored region', ['type' => 'object', 'properties' => [
        'account' => ['type' => 'object', 'properties' => [
            'region' => ['type' => ['string', 'null'], 'x-mcp-header' => 'Region'],
        ]],
    ]], [])]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        ++$this->calls;
        return McpToolOutput::structured((object) $input->toArray());
    }
}
