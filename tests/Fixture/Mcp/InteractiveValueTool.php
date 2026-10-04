<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequest;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputResponses;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInteractiveTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Test\Common\Domain\Serialization\SampleCommand;
use Fight\Test\Common\Domain\Serialization\SampleQuery;
use Throwable;

final class InteractiveValueTool implements McpInteractiveTool
{
    public int $calls = 0;
    public int $resumes = 0;
    public ?ApplicationData $input = null;
    public ?McpInputResponses $responses = null;
    public ?McpProgressReporter $reporter = null;
    public ?Throwable $failure = null;
    public McpInputRequired|McpToolOutput $output;
    public bool $askAgain = false;

    public function __construct(private QueryBus|CommandBus|null $bus = null)
    {
        $this->output = McpInputRequired::fromRequests(['details' => McpInputRequest::form(
            'Provide a public label',
            ['type' => 'object', 'properties' => ['label' => ['type' => 'string', 'minLength' => 1]], 'required' => ['label']],
            [['field' => 'label', 'label' => 'private validation label', 'rules' => 'not_blank']]
        )]);
    }

    #[McpToolInfo('value.interactive', 'Request a public label', ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']], ['type' => 'object'], requiresFormElicitation: true)]
    #[Validation(rules: [['field' => 'id', 'label' => 'Value', 'rules' => 'not_blank']])]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput|McpInputRequired
    {
        ++$this->calls;
        $this->input = $input;
        $this->reporter = $progress;
        return $this->output;
    }

    public function resume(ApplicationData $input, McpInputResponses $responses, McpProgressReporter $progress): McpToolOutput|McpInputRequired
    {
        ++$this->resumes;
        $this->input = $input;
        $this->responses = $responses;
        $this->reporter = $progress;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->askAgain) {
            return $this->output;
        }
        $response = $responses->get('details');
        $result = ['id' => $input->get('id'), 'action' => $response->action()];
        if ($response->action() === 'accept') {
            if ($this->bus instanceof QueryBus) {
                $result['value'] = $this->bus->fetch(new SampleQuery($input->get('id')));
            } elseif ($this->bus instanceof CommandBus) {
                $this->bus->execute(new SampleCommand($input->get('id')));
            }
            $result['label'] = $response->content()->get('label');
        }
        return McpToolOutput::structured($result);
    }
}
