<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Throwable;

final class EchoTool implements McpTool
{
    public ?ApplicationData $input = null;
    public ?McpProgressReporter $reporter = null;
    public int $calls = 0;
    public ?Throwable $failure = null;
    public mixed $output = null;

    #[McpToolInfo(name: 'echo', description: 'Echo validated values', inputSchema: ['type' => 'object'], outputSchema: [])]
    #[Validation]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        ++$this->calls;
        $this->input = $input;
        $this->reporter = $progress;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return McpToolOutput::structured($this->output ?? (object) $input->toArray());
    }
}
