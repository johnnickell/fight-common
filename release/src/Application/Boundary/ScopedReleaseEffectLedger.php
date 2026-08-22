<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface ScopedReleaseEffectLedger
 *
 * Exposes an invocation-local view over a reusable ordered effect ledger.
 */
interface ScopedReleaseEffectLedger extends ReleaseEffectLedger
{
    /**
     * Starts one new public operation scope without changing configured outcomes
     */
    public function beginEffectScope(): void;
}
