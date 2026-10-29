<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution;

use Fight\Common\Application\Mcp\Execution\Observation\Data\ActivityPage;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Mcp\Observation\Query\ListPhaseActivity;

/**
 * Class ListPhaseActivityTool
 */
final readonly class ListPhaseActivityTool implements McpTool
{
    /**
     * Constructs ListPhaseActivityTool
     */
    public function __construct(private QueryBus $queries)
    {
    }

    /**
     * @inheritDoc
     */
    #[McpToolInfo(
        'execution.activity',
        'Reads safe live phase activity and separately permitted detail references, never protected bodies.',
        ExecutionToolSchemas::ACTIVITY_INPUT,
        ExecutionToolSchemas::ACTIVITY_OUTPUT
    )]
    public function handle(ApplicationData $data, McpProgressReporter $progress): McpToolOutput
    {
        $input = ExecutionToolInput::read($data, ExecutionToolSchemas::ACTIVITY_INPUT);
        /**
         * @var ActivityPage|null $result
         */
        $result = $this->queries->fetch(new ListPhaseActivity(
            $input['project_id'],
            $input['session_id'],
            $input['phase_id'],
            $input['page'] ?? null,
            $input['per_page'] ?? null,
            $input['filters'] ?? [],
            ['recorded_at' => $input['direction'] ?? 'DESC']
        ));

        return ExecutionToolOutput::create($result);
    }
}
