<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Tool\Session;

use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolInput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolOutput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolSchemas;
use Fight\Common\Application\Mcp\Session\Data\ExecutionInspection;
use Fight\Common\Application\Mcp\Tool\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;

/**
 * Class GetExecutionTool
 */
final readonly class GetExecutionTool implements McpTool
{
    /**
     * Constructs GetExecutionTool
     */
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @inheritDoc
     */
    #[McpToolInfo(
        'execution.get',
        'Reads a selected execution and its authorized full summary, or null for missing or concealed evidence.',
        ExecutionToolSchemas::GET_INPUT,
        ExecutionToolSchemas::INSPECTION
    )]
    public function handle(ApplicationData $data, McpProgressReporter $progress): McpToolOutput
    {
        $input = ExecutionToolInput::read($data, ExecutionToolSchemas::GET_INPUT);
        /**
         * @var ExecutionInspection|null $result
         */
        $result = $this->queryBus->fetch(new GetExecution($input['project_id'], $input['session_id']));

        return ExecutionToolOutput::create($result);
    }
}
