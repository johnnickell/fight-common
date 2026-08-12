<?php

declare(strict_types=1);

namespace Fight\Common\Application\EventSourcing;

/**
 * Interface PublicationFailureRecorder
 *
 * Records portable operational evidence for completed publication fan-out
 */
interface PublicationFailureRecorder
{
    /**
     * Records one aggregated publication failure
     */
    public function record(EventPublicationFailure $failure): void;
}
