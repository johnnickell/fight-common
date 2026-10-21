<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Observation\Exception;

use RuntimeException;

/**
 * Class RecordingDenied
 */
final class RecordingDenied extends RuntimeException
{
    /**
     * Constructs RecordingDenied
     */
    public function __construct()
    {
        parent::__construct('Observation recording is not permitted.');
    }
}
