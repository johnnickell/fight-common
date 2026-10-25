<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Mcp\Prompt\McpPrompt;
use Fight\Common\Application\Mcp\Prompt\McpPromptInfo;
use Fight\Common\Application\Mcp\Prompt\McpPromptOutput;
use Throwable;

final class PromptProvider implements McpPrompt
{
    public int $metadataCalls = 0;
    public array $calls = [];
    public ?Throwable $failure = null;
    public McpPromptOutput $output;

    public function __construct(public McpPromptInfo $definition)
    {
        $this->output = McpPromptOutput::fromArray(['messages' => []]);
    }

    public function info(): McpPromptInfo
    {
        ++$this->metadataCalls;
        return $this->definition;
    }

    public function get(array $arguments): McpPromptOutput
    {
        $this->calls[] = $arguments;
        if ($this->failure !== null) { throw $this->failure; }
        return $this->output;
    }
}
