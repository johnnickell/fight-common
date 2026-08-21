<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

/**
 * Interface AuthorizationPort
 */
interface AuthorizationPort
{
    /**
     * Checks release authority
     */
    public function check(): ReleaseBoundaryOperationResult;
}
