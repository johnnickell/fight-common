<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution;

use Fight\Common\Domain\Execution\Observation;

/**
 * Interface ObservationAdmission
 *
 * Trusted consumer composition supplies current producer/project/session/phase policy
 */
interface ObservationAdmission
{
    /**
     * Validates current permission for every submission including retries
     *
     * IDs and metadata are references, not credentials. Throw RecordingDenied on denial.
     */
    public function admit(Observation $observation): void;
}
