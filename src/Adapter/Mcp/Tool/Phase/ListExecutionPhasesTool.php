<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Tool\Phase;

use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolInput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolOutput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolSchemas;
use Fight\Common\Application\Mcp\Phase\Data\PhasePage;
use Fight\Common\Application\Mcp\Tool\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Mcp\Phase\Query\ListExecutionPhases;

/**
 * Class ListExecutionPhasesTool
 */
final readonly class ListExecutionPhasesTool implements McpTool
{
    /**
     * Constructs ListExecutionPhasesTool
     */
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @inheritDoc
     */
    #[McpToolInfo(
        'execution.phases',
        'Reads live authorized phase evidence; observed outcomes do not establish Workflow or review acceptance.',
        ExecutionToolSchemas::PHASE_INPUT,
        ExecutionToolSchemas::PHASE_OUTPUT
    )]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        $arguments = ExecutionToolInput::read($input, ExecutionToolSchemas::PHASE_INPUT);
        /**
         * @var PhasePage|null $phasePage
         */
        $phasePage = $this->queryBus->fetch(new ListExecutionPhases(
            $arguments['project_id'],
            $arguments['session_id'],
            $arguments['page'] ?? null,
            $arguments['per_page'] ?? null,
            $arguments['filters'] ?? [],
            ['latest_recorded_at' => $arguments['direction'] ?? 'DESC']
        ));

        return ExecutionToolOutput::create($phasePage);
    }
}
