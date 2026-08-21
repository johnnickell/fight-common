<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

/**
 * Interface FilesystemPort
 */
interface FilesystemPort
{
    /**
     * Reads a boundary file without exposing a provider
     */
    public function read(string $path): ReleaseBoundaryOperationResult;

    /**
     * Checks whether a boundary path is a directory
     */
    public function isDirectory(string $path): ReleaseBoundaryPredicateResult;

    /**
     * Checks whether a boundary path accepts writes
     */
    public function isWritable(string $path): ReleaseBoundaryPredicateResult;

    /**
     * Checks whether a boundary artifact exists
     */
    public function exists(string $path): ReleaseBoundaryPredicateResult;

    /**
     * Checks that an output stays below the release runs root
     */
    public function resolveRunsDirectory(string $path, string $runsDirectory): RunsDirectoryResolutionResult;
}
