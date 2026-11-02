<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

/**
 * Interface TaskReadAdmission
 */
interface TaskReadAdmission
{
    /**
     * Returns current read authority before lookup without deriving it from possession
     */
    public function allowsRead(string $scope, string $id): bool;

    /**
     * Returns trusted stable caller and visibility epoch binding, rotating after any visibility discontinuity
     *
     * This bounded reference is not permission and must agree across processes and reconnects
     */
    public function comparisonScope(string $scope, string $id): string;
}
