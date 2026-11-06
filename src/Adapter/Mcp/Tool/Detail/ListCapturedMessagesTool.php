<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Tool\Detail;

use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolInput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolOutput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolSchemas;
use Fight\Common\Application\Mcp\Detail\Data\MessagePage;
use Fight\Common\Application\Mcp\Tool\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;
use Fight\Common\Domain\Mcp\Detail\Query\ListCapturedMessages;

/**
 * Class ListCapturedMessagesTool
 */
final readonly class ListCapturedMessagesTool implements McpTool
{
    /**
     * Constructs ListCapturedMessagesTool
     */
    public function __construct(private QueryBus $queryBus)
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
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        $arguments = ExecutionToolInput::read($input, ExecutionToolSchemas::MESSAGES_INPUT);
        /**
         * @var MessagePage|null $messagePage
         */
        $messagePage = $this->queryBus->fetch(new ListCapturedMessages(
            DetailSelection::create(
                $arguments['project_id'],
                $arguments['session_id'],
                $arguments['phase_id'],
                $arguments['producer_id'],
                $arguments['activity_id']
            ),
            $arguments['page'] ?? null,
            $arguments['per_page'] ?? null,
            ['recorded_at' => $arguments['direction'] ?? 'DESC']
        ));

        return ExecutionToolOutput::create($messagePage);
    }
}
