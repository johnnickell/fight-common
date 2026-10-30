<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Closure;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Task\McpTaskHandle;
use Fight\Common\Application\Mcp\Task\McpTaskTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;

final class TaskTool implements McpTaskTool
{
    public int $starts = 0;
    public ?Closure $onStart = null;
    public bool $ordinary = false;

    public function __construct(private readonly TaskFixture $fixture) {}

    #[McpToolInfo('example.operation', 'Start a bounded operation', ['type' => 'object', 'additionalProperties' => false], ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false])]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        return McpToolOutput::structured((object) ['ok' => true]);
    }

    public function start(ApplicationData $input, McpProgressReporter $progress): McpTaskHandle|McpToolOutput
    {
        ++$this->starts;
        if ($this->ordinary) { return $this->handle($input, $progress); }
        $this->fixture->create(60000, 25);
        ($this->onStart ?? static fn () => null)();
        return new McpTaskHandle(TaskFixture::ID);
    }
}
