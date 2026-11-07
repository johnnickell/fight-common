<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Tool\Detail;

use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolInput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolOutput;
use Fight\Common\Adapter\Mcp\Tool\Execution\ExecutionToolSchemas;
use Fight\Common\Application\Mcp\Detail\Data\CapturedDetail;
use Fight\Common\Application\Mcp\Tool\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Mcp\Detail\Query\GetCapturedDetail;

/**
 * Class GetCapturedDetailTool
 */
final readonly class GetCapturedDetailTool implements McpTool
{
    /**
     * Constructs GetCapturedDetailTool
     */
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @inheritDoc
     */
    #[McpToolInfo(
        'execution.detail',
        'Reads a separately authorized Base64 byte chunk; end of captured bytes does not prove complete capture.',
        ExecutionToolSchemas::DETAIL_INPUT,
        ExecutionToolSchemas::CAPTURED_DETAIL
    )]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        $arguments = ExecutionToolInput::read($input, ExecutionToolSchemas::DETAIL_INPUT);
        /**
         * @var CapturedDetail|null $capturedDetail
         */
        $capturedDetail = $this->queryBus->fetch(GetCapturedDetail::fromArray([
            'selection' => [
                'project_id'  => $arguments['project_id'],
                'session_id'  => $arguments['session_id'],
                'phase_id'    => $arguments['phase_id'],
                'producer_id' => $arguments['producer_id'],
                'activity_id' => $arguments['activity_id']
            ],
            'detail_id' => $arguments['detail_id'],
            'kind'      => $arguments['kind'],
            'offset'    => $arguments['offset'] ?? 0,
            'length'    => $arguments['length'] ?? 4096
        ]));

        return ExecutionToolOutput::create($capturedDetail);
    }
}
