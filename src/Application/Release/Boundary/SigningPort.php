<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

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
