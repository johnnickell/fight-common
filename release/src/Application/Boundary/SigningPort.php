<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface SigningPort
 */
interface SigningPort
{
    /**
     * Verifies one release signature
     */
    public function verify(): ReleaseBoundaryOperationResult;
}
