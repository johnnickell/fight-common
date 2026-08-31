<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface DependencyLanePort
 *
 * Resolves the package dependency modes which form part of compatibility evidence.
 */
interface DependencyLanePort
{
    /**
     * Returns one complete, attributed dependency-lane receipt
     *
     * @return array<string, mixed>
     */
    public function verify(string $repository, string $workspace): array;
}
