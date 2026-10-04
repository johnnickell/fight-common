<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Test\Common\Domain\Serialization\SampleQuery;

/**
 * Class DocumentLinkTool
 */
final readonly class DocumentLinkTool implements McpTool
{
    /**
     * Constructs DocumentLinkTool
     */
    public function __construct(private QueryBus $queries, private McpResourceInfo $document)
    {
    }

    #[McpToolInfo(
        name: 'document.find',
        description: 'Find one public document revision',
        inputSchema: [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'string']],
            'required' => ['id'],
            'additionalProperties' => false
        ],
        outputSchema: [
            'type' => 'object',
            'properties' => ['summary' => ['type' => 'string']],
            'required' => ['summary'],
            'additionalProperties' => false
        ]
    )]
    /**
     * @inheritDoc
     */
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        $result = $this->queries->fetch(new SampleQuery($input->get('id')));

        return McpToolOutput::structuredWithResourceLinks((object) ['summary' => $result['summary']], $this->document);
    }
}
