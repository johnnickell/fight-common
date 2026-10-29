<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

/**
 * Interface TaskUpdateAdmission
 */
interface TaskUpdateAdmission
{
    /**
     * Returns independent current producer authority before concealed lookup including retries
     */
    public function allowsUpdate(string $scope, string $id, string $producer): bool;
}
