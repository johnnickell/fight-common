<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution;

use Fight\Common\Application\Execution\Detail\Data\MessagePage;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Execution\Detail\DetailSelection;
use Fight\Common\Domain\Execution\Detail\Query\ListCapturedMessages;

/**
 * Class ListCapturedMessagesTool
 */
final readonly class ListCapturedMessagesTool implements McpTool
{
    /**
     * Constructs ListCapturedMessagesTool
     */
    public function __construct(private QueryBus $queries)
    {
    }

    /**
     * @inheritDoc
     */
    #[McpToolInfo(
        'execution.messages',
        'Reads a live message page with separately authorized Base64 initial chunks; references grant no body access.',
        ExecutionToolSchemas::MESSAGES_INPUT,
        ExecutionToolSchemas::MESSAGES_OUTPUT
    )]
    public function handle(ApplicationData $data, McpProgressReporter $progress): McpToolOutput
    {
        $input = ExecutionToolInput::read($data, ExecutionToolSchemas::MESSAGES_INPUT);
        /**
         * @var MessagePage|null $result
         */
        $result = $this->queries->fetch(new ListCapturedMessages(
            DetailSelection::create(
                $input['project_id'],
                $input['session_id'],
                $input['phase_id'],
                $input['producer_id'],
                $input['activity_id']
            ),
            $input['page'] ?? null,
            $input['per_page'] ?? null,
            ['recorded_at' => $input['direction'] ?? 'DESC']
        ));

        return ExecutionToolOutput::create($result);
    }
}
