<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface ReleaseEffectLedger
 *
 * Provides deterministic effect outcomes and their observable ledger.
 */
interface ReleaseEffectLedger
{
    /**
     * Configures one deterministic effect outcome
     */
    public function configureOutcome(string $effectClass, string $outcome): bool;

    /**
     * Returns the recorded boundary effects in order
     *
     * @return list<array{capability: string, effect_class: string, outcome: string}>
     */
    public function effects(): array;
}
