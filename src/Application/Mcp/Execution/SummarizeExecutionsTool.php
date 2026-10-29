<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution;

use Fight\Common\Application\Mcp\Execution\Session\Data\ExecutionSummary;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Session\Query\SummarizeExecutions;

/**
 * Class SummarizeExecutionsTool
 */
final readonly class SummarizeExecutionsTool implements McpTool
{
    /**
     * Constructs SummarizeExecutionsTool
     */
    public function __construct(private QueryBus $queries)
    {
    }

    /**
     * @inheritDoc
     */
    #[McpToolInfo(
        'execution.summarize',
        'Reads full-selection authorized persisted execution statistics, not Workflow truth or complete capture.',
        ExecutionToolSchemas::SUMMARY_INPUT,
        ExecutionToolSchemas::SUMMARY
    )]
    public function handle(ApplicationData $data, McpProgressReporter $progress): McpToolOutput
    {
        $input = ExecutionToolInput::read($data, ExecutionToolSchemas::SUMMARY_INPUT);
        /**
         * @var ExecutionSummary $result
         */
        $result = $this->queries->fetch(new SummarizeExecutions(
            ExecutionSelection::create($input['projects'], $input['filters'] ?? [])
        ));

        return ExecutionToolOutput::create($result);
    }
}
