<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\Service;

/**
 * Interface TaskInputAdmission
 */
interface TaskInputAdmission
{
    /**
     * Returns current submission authority independently of Task-read permission
     */
    public function allowsSubmission(string $scope, string $id, string $caller): bool;

    /**
     * Returns current protected answer-observation authority independently of submission
     */
    public function allowsObservation(string $scope, string $id, string $caller): bool;

    /**
     * Returns current business continuation authority for this exact bound group
     */
    public function allowsContinuation(string $scope, string $id, string $caller, string $group): bool;
}
