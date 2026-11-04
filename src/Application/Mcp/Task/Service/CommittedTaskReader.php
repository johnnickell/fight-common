<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Fight\Common\Domain\Mcp\Task\Task;

/**
 * Interface CommittedTaskReader
 *
 * Supplies protected committed aggregates for independently admitted Queries and commit acknowledgment
 */
interface CommittedTaskReader
{
    /**
     * Returns committed coherent protected state without mutation or uncommitted caller work
     */
    public function read(string $scope, string $id): ?Task;
}
