<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\Service;

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
    public function allowsRemoval(string $scope, string $id): bool;
}
