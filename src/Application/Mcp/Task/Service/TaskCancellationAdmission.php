<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Fight\Common\Domain\Mcp\Task\TaskId;

/**
 * Interface TaskCancellationAdmission
 */
interface TaskCancellationAdmission
{
    /**
     * Returns current cancellation authority independently of read and worker control
     */
    public function allowsCancellation(string $scope, TaskId $id, string $caller): bool;

    /**
     * Returns current protected intent observation authority independently of cancellation
     */
    public function allowsCancellationObservation(string $scope, TaskId $id, string $caller): bool;
}
