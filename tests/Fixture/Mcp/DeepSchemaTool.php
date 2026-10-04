<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Validation\Data\ApplicationData;
use LogicException;

final class DeepSchemaTool implements McpTool
{
    private const array DEPTH_8 = ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => true]]]]]]]];
    private const array DEPTH_16 = ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => self::DEPTH_8]]]]]]]];
    private const array DEPTH_24 = ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => self::DEPTH_16]]]]]]]];
    private const array DEPTH_32 = ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => self::DEPTH_24]]]]]]]];
    private const array DEPTH_40 = ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => self::DEPTH_32]]]]]]]];
    private const array DEPTH_48 = ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => self::DEPTH_40]]]]]]]];
    private const array DEPTH_56 = ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => self::DEPTH_48]]]]]]]];
    private const array DEPTH_64 = ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => ['not' => self::DEPTH_56]]]]]]]];

    #[McpToolInfo('deep.schema', 'Deep declaration', ['type' => 'object', ...self::DEPTH_64], self::DEPTH_64)]
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
    {
        throw new LogicException('Discovery must never invoke a Tool.');
    }
}
