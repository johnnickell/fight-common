<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

use InvalidArgumentException;

/**
 * Class BaselineTagResolutionResult
 */
final readonly class BaselineTagResolutionResult
{
    /**
     * Constructs BaselineTagResolutionResult
     */
    private function __construct(
        public ReleaseBoundaryOutcome $outcome,
        public ?BaselineTagResolutionStatus $status,
        public ?string $tagName,
        public ?string $tagObjectOid,
        public ?string $peeledCommitOid
    ) {
    }

    /**
     * Creates one fully resolved baseline reference
     */
    public static function resolved(string $tagName, string $tagObjectOid, string $peeledCommitOid): self
    {
        return new self(
            ReleaseBoundaryOutcome::SUCCESS,
            BaselineTagResolutionStatus::RESOLVED,
            $tagName,
            $tagObjectOid,
            $peeledCommitOid
        );
    }

    /**
     * Creates one successful Git lookup whose policy state blocks use as a baseline
     */
    public static function rejected(BaselineTagResolutionStatus $status): self
    {
        if ($status === BaselineTagResolutionStatus::RESOLVED) {
            throw new InvalidArgumentException('A rejected baseline resolution cannot be resolved.');
        }

        return new self(ReleaseBoundaryOutcome::SUCCESS, $status, null, null, null);
    }

    /**
     * Creates one governed provider stop without claiming resolved identities
     */
    public static function stopped(ReleaseBoundaryOutcome $outcome): self
    {
        if (
            $outcome === ReleaseBoundaryOutcome::SUCCESS
            || $outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED
        ) {
            throw new InvalidArgumentException('A stopped baseline resolution requires a non-success outcome.');
        }

        return new self($outcome, null, null, null, null);
    }

    /**
     * Reports whether immutable tag identities were resolved
     */
    public function isResolved(): bool
    {
        return $this->outcome === ReleaseBoundaryOutcome::SUCCESS
            && $this->status === BaselineTagResolutionStatus::RESOLVED;
    }
}
