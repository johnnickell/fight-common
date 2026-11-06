<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Tool\Session;

use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolInput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolOutput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolSchemas;
use Fight\Common\Application\Mcp\Session\Data\ExecutionPage;
use Fight\Common\Application\Mcp\Tool\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Session\Query\ListExecutions;

/**
 * Class ListExecutionsTool
 */
final readonly class ListExecutionsTool implements McpTool
{
    /**
     * Constructs ListExecutionsTool
     */
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @inheritDoc
     */
    #[McpToolInfo(
        'execution.list',
        'Reads a bounded live authorized execution page; catalog cursors grant no execution access.',
        ExecutionToolSchemas::LIST_INPUT,
        ExecutionToolSchemas::LIST_OUTPUT
    )]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        $arguments = ExecutionToolInput::read($input, ExecutionToolSchemas::LIST_INPUT);
        /**
         * @var ExecutionPage $executionPage
         */
        $executionPage = $this->queryBus->fetch(new ListExecutions(
            ExecutionSelection::create($arguments['projects'], $arguments['filters'] ?? []),
            $arguments['page'] ?? null,
            $arguments['per_page'] ?? null,
            ['latest_recorded_at' => $arguments['direction'] ?? 'DESC']
        ));

        return ExecutionToolOutput::create($executionPage);
    }
}
