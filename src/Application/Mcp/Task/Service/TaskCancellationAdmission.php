<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

/**
 * Interface TaskCancellationAdmission
 */
interface TaskCancellationAdmission
{
    /**
     * Returns current cancellation authority independently of read and worker control
     */
    public function allowsCancellation(string $scope, string $id, string $caller): bool;

    /**
     * Returns current protected intent observation authority independently of cancellation
     */
    public function allowsCancellationObservation(string $scope, string $id, string $caller): bool;
}
