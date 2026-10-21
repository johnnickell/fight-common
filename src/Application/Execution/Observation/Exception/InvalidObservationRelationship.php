<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Observation\Exception;

use RuntimeException;

/**
 * Class InvalidObservationRelationship
 */
final class InvalidObservationRelationship extends RuntimeException
{
    /**
     * Constructs InvalidObservationRelationship
     */
    public function __construct()
    {
        parent::__construct('Observation relationship is unavailable or inconsistent.');
    }
}
