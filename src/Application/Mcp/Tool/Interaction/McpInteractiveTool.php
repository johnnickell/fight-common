<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Interaction;

use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Validation\Data\ApplicationData;

/**
 * Interface McpInteractiveTool
 */
interface McpInteractiveTool extends McpTool
{
    /**
     * Handles an ordinary retry with restored arguments and validated keyed responses
     *
     * Declare metadata and initial Validation only on handle, including requiresFormElicitation.
     * Ordinary state is replayable. Decline and cancel carry no content and require a safe terminal outcome.
     */
    public function resume(
        ApplicationData $input,
        McpInputResponses $responses,
        McpProgressReporter $progress
    ): McpToolOutput|McpInputRequired;
}
