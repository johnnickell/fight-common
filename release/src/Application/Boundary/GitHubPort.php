<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface GitHubPort
 */
interface GitHubPort
{
    /**
     * Creates one GitHub release
     */
    public function release(): ReleaseBoundaryOperationResult;
}
