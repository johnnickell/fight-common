<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface GitPort
 */
interface GitPort
{
    /**
     * Returns one read-only repository inspection outcome
     */
    public function inspectRepository(): ReleaseBoundaryOperationResult;

    /**
     * Resolves one immutable Git reference
     */
    public function resolveBaselineTag(string $tagName, string $candidateOid): BaselineTagResolutionResult;
}
