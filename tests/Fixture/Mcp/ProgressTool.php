<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Closure;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Validation\Data\ApplicationData;

final class ProgressTool implements McpTool
{
    public int $calls = 0;
    public ?McpProgressReporter $reporter = null;
    public ?ApplicationData $input = null;

    public function __construct(private readonly Closure $behavior) {}

    #[McpToolInfo('progress', 'Report work', ['type' => 'object'], ['type' => 'string'])]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        ++$this->calls;
        $this->reporter = $progress;
        $this->input = $input;
        return ($this->behavior)($progress);
    }
}
