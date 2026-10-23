<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Observation\Service;

use Fight\Common\Application\Execution\Observation\Data\ActivityPage;
use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Execution\Observation\Query\ListPhaseActivity;

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
