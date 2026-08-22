<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

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
