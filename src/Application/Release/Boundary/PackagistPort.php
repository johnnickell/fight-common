<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

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
