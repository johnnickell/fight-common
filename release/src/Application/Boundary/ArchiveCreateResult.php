<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Class ArchiveCreateResult
 *
 * Owns a classified archive-creation outcome and its observable properties.
 */
final readonly class ArchiveCreateResult
{
    /**
     * Constructs ArchiveCreateResult
     */
    private function __construct(
        public ReleaseBoundaryOutcome $outcome,
        public ?string $archivePath,
        public ?string $sha256Digest
    ) {
    }

    /**
     * Encodes one exact completed archive
     */
    public static function created(string $archivePath, string $sha256Digest): self
    {
        return new self(ReleaseBoundaryOutcome::SUCCESS, $archivePath, $sha256Digest);
    }

    /**
     * Reports an archive observed as already existing
     */
    public static function alreadySatisfied(string $sha256Digest): self
    {
        return new self(ReleaseBoundaryOutcome::ALREADY_SATISFIED, null, $sha256Digest);
    }

    /**
     * Records one classified stopped archive operation
     */
    public static function stopped(ReleaseBoundaryOutcome $outcome): self
    {
        return new self($outcome, null, null);
    }

    /**
     * Reports whether an archive was produced by this operation
     */
    public function hasArchive(): bool
    {
        return $this->outcome === ReleaseBoundaryOutcome::SUCCESS && is_string($this->archivePath);
    }
}
