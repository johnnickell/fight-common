<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Tool\Session;

use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolInput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolOutput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolSchemas;
use Fight\Common\Application\Mcp\Session\Data\ExecutionSummary;
use Fight\Common\Application\Mcp\Tool\McpProgressReporter;
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
    public function __construct(private QueryBus $queryBus)
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
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        $arguments = ExecutionToolInput::read($input, ExecutionToolSchemas::SUMMARY_INPUT);
        /**
         * @var ExecutionSummary $executionSummary
         */
        $executionSummary = $this->queryBus->fetch(new SummarizeExecutions(
            ExecutionSelection::create($arguments['projects'], $arguments['filters'] ?? [])
        ));

        return ExecutionToolOutput::create($executionSummary);
    }
}
