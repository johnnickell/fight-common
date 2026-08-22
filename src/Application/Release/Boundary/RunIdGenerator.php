<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

/**
 * Interface RunIdGenerator
 *
 * Generates one unique identity for each release execution attempt.
 */
interface RunIdGenerator
{
    /**
     * Generates one lowercase SHA-256-width run identity
     */
    public function generate(): string;
}
