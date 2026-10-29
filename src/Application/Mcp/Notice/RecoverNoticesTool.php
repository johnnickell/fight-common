<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice;

use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Notice\Data\NoticeRecoveryBatch;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\McpToolSchemaValidator;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\RecoverNotices;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class RecoverNoticesTool
 */
final readonly class RecoverNoticesTool implements McpTool
{
    /**
     * Constructs RecoverNoticesTool
     */
    public function __construct(private QueryBus $queries, private int $maxResponseBytes = 1048576)
    {
        if ($maxResponseBytes < 1 || $maxResponseBytes > 1048576) {
            throw new DomainException('Invalid notice Tool response budget.');
        }
    }

    /**
     * @inheritDoc
     */
    #[McpToolInfo(
        'notices.recover',
        'Recovers bounded authorized retained notices; continuation is not authority or a processing acknowledgment.',
        NoticeToolSchemas::INPUT,
        NoticeToolSchemas::OUTPUT
    )]
    public function handle(ApplicationData $data, McpProgressReporter $progress): McpToolOutput
    {
        $json = StrictJson::fromObject($data->toArray());
        if (!McpToolSchemaValidator::matches($json, StrictJson::fromObject(NoticeToolSchemas::INPUT))) {
            throw new DomainException('Invalid notice Tool selection.');
        }

        $input = json_decode($json->toString(), true, flags: JSON_THROW_ON_ERROR);
        /**
         * @var NoticeRecoveryBatch $result
         */
        $result = $this->queries->fetch(new RecoverNotices(
            NoticeSelection::create($input['scopes'], $input['filters'] ?? []),
            $input['continuation'] ?? null,
            $input['batch_size'] ?? 32,
            $input['scan_limit'] ?? 100
        ));
        $projection = $result->toArray();
        foreach ($projection['notices'] as &$notice) {
            $notice['metadata'] = (object) $notice['metadata'];
        }

        unset($notice);

        return McpToolOutput::boundedStructured($projection, $this->maxResponseBytes);
    }
}
