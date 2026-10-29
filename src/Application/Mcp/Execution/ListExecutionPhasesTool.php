<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution;

use Fight\Common\Application\Mcp\Execution\Phase\Data\PhasePage;
use Fight\Common\Application\Mcp\McpProgressReporter;
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
    public function __construct(private QueryBus $queries)
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
    public function handle(ApplicationData $data, McpProgressReporter $progress): McpToolOutput
    {
        $input = ExecutionToolInput::read($data, ExecutionToolSchemas::PHASE_INPUT);
        /**
         * @var PhasePage|null $result
         */
        $result = $this->queries->fetch(new ListExecutionPhases(
            $input['project_id'],
            $input['session_id'],
            $input['page'] ?? null,
            $input['per_page'] ?? null,
            $input['filters'] ?? [],
            ['latest_recorded_at' => $input['direction'] ?? 'DESC']
        ));

        return ExecutionToolOutput::create($result);
    }
}
