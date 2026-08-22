<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface PackagistPort
 */
interface PackagistPort
{
    /**
     * Performs one Packagist publication
     */
    public function publish(): ReleaseBoundaryOperationResult;
}
