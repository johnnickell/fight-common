<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Phase\Service;

use Fight\Common\Application\Execution\Phase\Data\PhasePage;
use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Execution\Phase\Query\ListExecutionPhases;

/**
 * Interface PhaseReader
 */
interface PhaseReader
{
    /**
     * Returns permitted phase rows or null for a missing or concealed execution
     */
    public function list(ListExecutionPhases $query, ExecutionAccess $access): ?PhasePage;
}
