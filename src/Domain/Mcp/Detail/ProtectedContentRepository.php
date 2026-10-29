<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Detail;

use Fight\Common\Domain\Mcp\Observation\Observation;

/**
 * Interface ProtectedContentRepository
 *
 * Trusted recording storage, not an authorized public detail Query
 */
interface ProtectedContentRepository
{
    /**
     * Returns retained protected comparison content for the safe detail observation
     */
    public function getFor(Observation $detail): ?ProtectedContent;

    /**
     * Adds content within the same transaction as its safe identity and link
     */
    public function add(Observation $detail, ProtectedContent $content): void;

    /**
     * Removes protected content while retaining safe identity and links in the shared transaction
     *
     * An already missing protected row is a no-op, not recording replay or permission
     */
    public function removeFor(Observation $detail): void;
}
