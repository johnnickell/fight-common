<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution;

use Fight\Common\Application\Execution\Session\Data\ExecutionInspection;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Execution\Session\Query\GetExecution;

/**
 * Class GetExecutionTool
 */
final readonly class GetExecutionTool implements McpTool
{
    /**
     * Constructs GetExecutionTool
     */
    public function __construct(private QueryBus $queries)
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
        $result = $this->queries->fetch(new GetExecution($input['project_id'], $input['session_id']));

        return ExecutionToolOutput::create($result);
    }
}
