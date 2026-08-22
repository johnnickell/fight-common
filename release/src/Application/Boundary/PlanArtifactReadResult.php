<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

use InvalidArgumentException;

/**
 * Class PlanArtifactReadResult
 *
 * Separates a missing immutable artifact from content and governed boundary stops.
 */
final readonly class PlanArtifactReadResult
{
    /**
     * Constructs PlanArtifactReadResult
     */
    private function __construct(
        public ReleaseBoundaryOutcome $outcome,
        public ?string $contents,
        public bool $missing
    ) {
    }

    /**
     * Reports regular-file content read through the artifact-store boundary
     */
    public static function content(string $contents): self
    {
        return new self(ReleaseBoundaryOutcome::SUCCESS, $contents, false);
    }

    /**
     * Reports that no final directory entry exists
     */
    public static function missing(): self
    {
        return new self(ReleaseBoundaryOutcome::SUCCESS, null, true);
    }

    /**
     * Reports a governed read stop without content or a missing claim
     */
    public static function stopped(ReleaseBoundaryOutcome $outcome): self
    {
        if (
            $outcome === ReleaseBoundaryOutcome::SUCCESS
            || $outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED
        ) {
            throw new InvalidArgumentException('A stopped artifact read requires a non-success outcome.');
        }

        return new self($outcome, null, false);
    }

    /**
     * Reports whether regular-file content was returned
     */
    public function hasContent(): bool
    {
        return $this->outcome === ReleaseBoundaryOutcome::SUCCESS
            && !$this->missing
            && is_string($this->contents);
    }
}
