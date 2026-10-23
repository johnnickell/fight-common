<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Read;

use Fight\Common\Application\Execution\Read\Query\ListExecutions;

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
}
