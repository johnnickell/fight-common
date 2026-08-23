<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use RuntimeException;

/**
 * Class CompatibilityManifestRejected
 *
 * Carries authenticated manifest-authority findings without parsing exception messages.
 */
final class CompatibilityManifestRejected extends RuntimeException
{
    /** @var non-empty-list<CompatibilityFinding> */
    public readonly array $findings;

    /**
     * Constructs CompatibilityManifestRejected
     */
    public function __construct(CompatibilityFinding $finding, CompatibilityFinding ...$additionalFindings)
    {
        $this->findings = [$finding, ...$additionalFindings];

        parent::__construct('Compatibility manifest authority rejected classified subjects.');
    }
}
