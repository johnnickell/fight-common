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
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        $arguments = ExecutionToolInput::read($input, ExecutionToolSchemas::GET_INPUT);
        /**
         * @var ExecutionInspection|null $executionInspection
         */
        $executionInspection = $this->queryBus->fetch(new GetExecution(
            $arguments['project_id'],
            $arguments['session_id']
        ));

        return ExecutionToolOutput::create($executionInspection);
    }
}
