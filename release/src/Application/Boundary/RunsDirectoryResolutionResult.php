<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

use InvalidArgumentException;

/**
 * Class RunsDirectoryResolutionResult
 *
 * Separates canonical runs-directory authority from a governed resolution stop.
 */
final readonly class RunsDirectoryResolutionResult
{
    /**
     * Constructs RunsDirectoryResolutionResult
     */
    private function __construct(
        public ReleaseBoundaryOutcome $outcome,
        public ?CanonicalRunsDirectory $directory
    ) {
    }

    /**
     * Returns one successfully resolved canonical directory
     */
    public static function success(CanonicalRunsDirectory $directory): self
    {
        return new self(ReleaseBoundaryOutcome::SUCCESS, $directory);
    }

    /**
     * Returns a completed resolution that rejected the supplied path
     */
    public static function rejected(): self
    {
        return new self(ReleaseBoundaryOutcome::SUCCESS, null);
    }

    /**
     * Returns a governed stop without canonical directory authority
     */
    public static function stopped(ReleaseBoundaryOutcome $outcome): self
    {
        if (
            $outcome === ReleaseBoundaryOutcome::SUCCESS
            || $outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED
        ) {
            throw new InvalidArgumentException('A stopped runs-directory resolution requires a non-success outcome.');
        }

        return new self($outcome, null);
    }

    /**
     * Reports whether canonical directory authority is available
     */
    public function hasDirectory(): bool
    {
        return $this->outcome === ReleaseBoundaryOutcome::SUCCESS
            && $this->directory instanceof CanonicalRunsDirectory;
    }
}
