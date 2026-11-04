<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Fight\Common\Domain\Mcp\Task\TaskId;

/**
 * Interface TaskInputAdmission
 */
interface TaskInputAdmission
{
    /**
     * Returns current submission authority independently of Task-read permission
     */
    public function allowsSubmission(string $scope, TaskId $id, string $caller): bool;

    /**
     * Returns current protected answer-observation authority independently of submission
     */
    public function allowsObservation(string $scope, TaskId $id, string $caller): bool;

    /**
     * Returns current business continuation authority for this exact bound group
     */
    public function allowsContinuation(string $scope, TaskId $id, string $caller, string $group): bool;
}
