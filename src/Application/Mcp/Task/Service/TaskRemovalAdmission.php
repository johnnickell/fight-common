<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Fight\Common\Domain\Mcp\Task\TaskId;

/**
 * Interface TaskRemovalAdmission
 */
interface TaskRemovalAdmission
{
    /**
     * Returns current independent removal permission including for missing identities
     *
     * Consumer policy owns eligibility and holds; no other Task grant confers this permission
     */
    public function allowsRemoval(string $scope, TaskId $id): bool;
}
