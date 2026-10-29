<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Observation\Service;

use Fight\Common\Application\Mcp\Execution\Observation\Data\ActivityPage;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Mcp\Observation\Query\ListPhaseActivity;

/**
 * Interface ActivityReader
 */
interface ActivityReader
{
    /**
     * Returns permitted activity and reference metadata or null for a concealed or missing phase
     */
    public function list(ListPhaseActivity $query, ExecutionAccess $access): ?ActivityPage;
}
