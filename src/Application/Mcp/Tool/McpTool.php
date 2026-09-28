<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Validation\Data\ApplicationData;

/**
 * Interface McpTool
 */
interface McpTool
{
    /**
     * Handles validated arguments and returns only public-safe semantic output
     *
     * Declare McpToolInfo on this method. Map inputs explicitly to existing use cases, never HTTP Actions.
     * Discovery never invokes this method. Invocation and output-schema conformance belong
     * to the selected-Tool invoker.
     */
    public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput;
}
