<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\Service;

/**
 * Interface TaskReadAdmission
 */
interface TaskReadAdmission
{
    /**
     * Returns current read authority before lookup without deriving it from possession
     */
    public function allowsRead(string $scope, string $id): bool;
}
