<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Session\Service;

use Fight\Common\Application\Mcp\Execution\Session\Data\ExecutionInspection;
use Fight\Common\Application\Mcp\Execution\Session\Data\ExecutionPage;
use Fight\Common\Application\Mcp\Execution\Session\Data\ExecutionSummary;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;
use Fight\Common\Domain\Mcp\Session\Query\ListExecutions;

/**
 * Interface ExecutionReader
 */
interface ExecutionReader
{
    /**
     * Returns bounded full-selection statistics constrained before calculation
     */
    public function summarize(ExecutionSelection $selection, ExecutionAccess $access): ExecutionSummary;

    /**
     * Returns a live page and permitted total constrained before selection
     */
    public function list(ListExecutions $query, ExecutionAccess $access): ExecutionPage;

    /**
     * Returns a selected summary or null for a missing or concealed execution
     */
    public function inspect(GetExecution $query, ExecutionAccess $access): ?ExecutionInspection;
}
