<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface ReleasePlanAuthorityPort
 *
 * Re-resolves the mutable policy and approval authority bound by an immutable release plan.
 */
interface ReleasePlanAuthorityPort
{
    /**
     * Returns the exact current-truth classification for all non-Git plan authority
     *
     * @param array<string, mixed> $plan Revalidated immutable plan.
     */
    public function revalidatePlanAuthority(array $plan): ReleasePlanAuthorityStatus;
}
