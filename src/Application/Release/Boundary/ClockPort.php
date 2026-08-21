<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

/**
 * Interface ClockPort
 */
interface ClockPort
{
    /**
     * Reads the release clock
     */
    public function now(): ReleaseBoundaryOperationResult;
}
