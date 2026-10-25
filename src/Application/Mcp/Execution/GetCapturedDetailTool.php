<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution;

use Fight\Common\Application\Execution\Detail\Data\CapturedDetail;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Execution\Detail\Query\GetCapturedDetail;

/**
 * Class GetCapturedDetailTool
 */
final readonly class GetCapturedDetailTool implements McpTool
{
    /**
     * Constructs GetCapturedDetailTool
     */
    public function __construct(private QueryBus $queries)
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
    public function handle(ApplicationData $data, McpProgressReporter $progress): McpToolOutput
    {
        $input = ExecutionToolInput::read($data, ExecutionToolSchemas::DETAIL_INPUT);
        /**
         * @var CapturedDetail|null $result
         */
        $result = $this->queries->fetch(GetCapturedDetail::fromArray([
            'selection' => [
                'project_id'  => $input['project_id'],
                'session_id'  => $input['session_id'],
                'phase_id'    => $input['phase_id'],
                'producer_id' => $input['producer_id'],
                'activity_id' => $input['activity_id']
            ],
            'detail_id' => $input['detail_id'],
            'kind'      => $input['kind'],
            'offset'    => $input['offset'] ?? 0,
            'length'    => $input['length'] ?? 4096
        ]));

        return ExecutionToolOutput::create($result);
    }
}
