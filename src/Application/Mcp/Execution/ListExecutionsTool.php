<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution;

use Fight\Common\Application\Execution\Session\Data\ExecutionPage;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Execution\Session\Query\ListExecutions;

/**
 * Class ListExecutionsTool
 */
final readonly class ListExecutionsTool implements McpTool
{
    /**
     * Constructs ListExecutionsTool
     */
    public function __construct(private QueryBus $queries)
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
    public function handle(ApplicationData $data, McpProgressReporter $progress): McpToolOutput
    {
        $input = ExecutionToolInput::read($data, ExecutionToolSchemas::LIST_INPUT);
        /**
         * @var ExecutionPage $result
         */
        $result = $this->queries->fetch(new ListExecutions(
            ExecutionSelection::create($input['projects'], $input['filters'] ?? []),
            $input['page'] ?? null,
            $input['per_page'] ?? null,
            ['latest_recorded_at' => $input['direction'] ?? 'DESC']
        ));

        return ExecutionToolOutput::create($result);
    }
}
