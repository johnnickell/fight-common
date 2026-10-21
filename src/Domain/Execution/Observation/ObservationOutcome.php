<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Observation;

/**
 * Enum ObservationOutcome
 */
enum ObservationOutcome: string
{
    case UNKNOWN = 'unknown';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
