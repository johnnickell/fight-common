<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task;

use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Validation\Data\ApplicationData;

/**
 * Interface McpTaskTool
 *
 * Additive opt-in; the released handle and interactive resume contracts remain unchanged.
 */
interface McpTaskTool extends McpTool
{
    /**
     * Starts a negotiated operation or returns an ordinary result for this invocation
     *
     * The invoker applies handle's metadata/schema/rules before entry. Consumers create through
     * CreateTask and own submission/reconciliation. Return a handle only after durable creation;
     * Common independently retrieves and authorizes current state before disclosing it.
     * Direct handle calls retain their ordinary contract and must not start inaccessible work.
     */
    public function start(
        ApplicationData $input,
        McpProgressReporter $progress
    ): McpTaskHandle|McpToolOutput|McpInputRequired;
}
