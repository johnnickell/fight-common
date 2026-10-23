<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Detail\Service;

use Fight\Common\Domain\Execution\Detail\Command\RemoveCapturedDetail;

/**
 * Interface DetailRemovalAdmission
 *
 * Consumer-owned current removal decision, independent of every read grant
 */
interface DetailRemovalAdmission
{
    /**
     * Validates current permission for the entire exact selection or throws DetailRemovalRejected
     *
     * Called inside the shared transaction before lookups, including repeated removal
     */
    public function admit(RemoveCapturedDetail $command): void;
}
