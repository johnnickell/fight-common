<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Fight\Common\Domain\Mcp\Task\TaskId;

/**
 * Interface TaskUpdateAdmission
 */
interface TaskUpdateAdmission
{
    /**
     * Returns independent current producer authority before concealed lookup including retries
     */
    public function allowsUpdate(string $scope, TaskId $id, string $producer): bool;
}
