<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Test\Common\Domain\Serialization\SampleQuery;

final readonly class ReadValueTool implements McpTool
{
    public function __construct(private QueryBus $queries) {}

    #[McpToolInfo(
        name: 'value.read',
        description: 'Read the public value',
        inputSchema: ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'minLength' => 1]], 'required' => ['id'], 'additionalProperties' => false],
        outputSchema: ['type' => 'object', 'properties' => ['value' => ['type' => 'string']], 'required' => ['value'], 'additionalProperties' => false],
    )]
    #[Validation(rules: [['field' => 'id', 'label' => 'Identifier', 'rules' => 'required|not_blank']])]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        $progress->report(0, 1, 'Reading');
        if ($progress->isCancelled()) {
            return McpToolOutput::structured((object) ['value' => '']);
        }
        $result = $this->queries->fetch(new SampleQuery($input->get('id')));
        $progress->report(1, 1, 'Read');

        return McpToolOutput::structured((object) ['value' => $result['value']]);
    }
}
