<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

/**
 * Interface PlanArtifactStore
 *
 * Persists immutable release-planning artifacts.
 */
interface PlanArtifactStore
{
    /**
     * Reads one artifact or fixture
     */
    public function readArtifact(
        CanonicalRunsDirectory $directory,
        string $filename
    ): PlanArtifactReadResult;

    /**
     * Writes one new immutable artifact
     */
    public function writeArtifact(
        CanonicalRunsDirectory $directory,
        string $filename,
        string $contents
    ): PlanArtifactWriteResult;

    /**
     * Checks that an output stays below the release runs root
     */
    public function resolveRunsDirectory(string $path, string $runsDirectory): RunsDirectoryResolutionResult;
}
