<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Closure;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequest;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputResponses;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInteractiveTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Throwable;

final class DestructiveValueTool implements McpInteractiveTool
{
    public int $calls = 0;
    public int $resumes = 0;
    public ?Throwable $failure = null;
    public bool $invalidOutput = false;
    public bool $report = false;
    public ?Closure $duringResume = null;
    public ?ApplicationData $restored = null;
    public ?McpInputResponses $responses = null;

    public function __construct(private readonly ?Closure $mutate = null) {}

    #[McpToolInfo('value.delete', 'Delete a fixture value', ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']], ['type' => 'object'], requiresFormElicitation: true)]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpInputRequired
    {
        ++$this->calls;
        return McpInputRequired::confirmation([
            'approval' => McpInputRequest::form('Confirm fixture deletion', [
                'type' => 'object', 'properties' => ['confirm' => ['type' => 'boolean']], 'required' => ['confirm']
            ]),
            'reason' => McpInputRequest::form('Provide a reason', [
                'type' => 'object', 'properties' => ['text' => ['type' => 'string']], 'required' => ['text']
            ], [['field' => 'text', 'label' => 'private reason', 'rules' => 'not_blank']])
        ]);
    }

    public function resume(ApplicationData $input, McpInputResponses $responses, McpProgressReporter $progress): McpToolOutput
    {
        ++$this->resumes;
        $this->restored = $input;
        $this->responses = $responses;
        if ($this->duringResume !== null) {
            ($this->duringResume)();
        }
        if ($this->report) {
            $progress->report(1, 2, 'Checking confirmation');
        }
        if ($progress->isCancelled()) {
            return McpToolOutput::structured(['cancelled' => true]);
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }
        // This fixture, not Common, decides whether accepted form content authorizes its mutation.
        if ($responses->get('approval')->content()->get('confirm') && $this->mutate !== null) {
            ($this->mutate)($input);
        }
        return McpToolOutput::structured($this->invalidOutput ? [] : ['deleted' => true]);
    }
}
