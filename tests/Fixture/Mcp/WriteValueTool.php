<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Test\Common\Domain\Serialization\SampleCommand;

final readonly class WriteValueTool implements McpTool
{
    public function __construct(private CommandBus $commands) {}

    #[McpToolInfo(
        name: 'value.write',
        description: 'Write a caller-identified value',
        inputSchema: ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'minLength' => 1]], 'required' => ['id'], 'additionalProperties' => false],
        outputSchema: ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id'], 'additionalProperties' => false],
    )]
    #[Validation(rules: [['field' => 'id', 'label' => 'Identifier', 'rules' => 'required|not_blank']])]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        $id = $input->get('id');
        $progress->report(0, 1, 'Writing');
        if ($progress->isCancelled()) {
            return McpToolOutput::structured((object) ['id' => $id]);
        }
        $this->commands->execute(new SampleCommand($id));
        $progress->report(1, 1, 'Written');

        return McpToolOutput::structured((object) ['id' => $id]);
    }
}
