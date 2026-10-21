<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Observation\Exception;

use RuntimeException;

/**
 * Class ObservationConflict
 */
final class ObservationConflict extends RuntimeException
{
    /**
     * Constructs ObservationConflict
     */
    public function __construct()
    {
        parent::__construct('Observation identity has conflicting content.');
    }
}
