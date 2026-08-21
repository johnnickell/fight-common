<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

/**
 * Interface HashingPort
 */
interface HashingPort
{
    /**
     * Returns the SHA-256 identity of immutable contents
     */
    public function sha256(string $contents): ReleaseBoundaryOperationResult;
}
