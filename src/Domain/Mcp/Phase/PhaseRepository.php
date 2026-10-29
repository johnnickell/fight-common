<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Phase;

/**
 * Interface PhaseRepository
 */
interface PhaseRepository
{
    /**
     * Returns a recorded subject by its project-scoped identity
     */
    public function getById(string $projectId, string $phaseId): ?Phase;

    /**
     * Adds an immutable phase inside the recording transaction
     */
    public function add(Phase $phase): void;
}
