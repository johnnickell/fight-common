<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface ArchivePort
 *
 * Creates deterministic rootless archives from an authenticated candidate commit.
 */
interface ArchivePort
{
    /**
     * Creates a deterministic rootless zip archive from one exact candidate commit
     *
     * The archive carries the name `fight-common-vX.Y.Z.zip` and contains only files
     * admitted by the committed export-ignore rules. File ordering and timestamps
     * are normalized so the same candidate and policy always produce identical bytes.
     *
     * @param string $candidateOid Exact candidate commit OID.
     * @param string $version Canonical SemVer version for the archive filename.
     * @param string $sourceRepositoryPath Absolute repository-root path.
     * @param array $exclusions Paths excluded from the archive by committed policy.
     *
     * @phpstan-param list<string> $exclusions
     */
    public function createArchive(
        string $candidateOid,
        string $version,
        string $sourceRepositoryPath,
        array $exclusions
    ): ArchiveCreateResult;

    /**
     * Returns the exact bounded effect set that createArchive would produce
     *
     * Derives the canonical set of included and excluded paths, archive name,
     * and version from one exact candidate commit without performing any mutation.
     *
     * @return ReleasePackageEffectSet|null Null when derivation is impossible.
     */
    public function deriveEffectSet(
        string $candidateOid,
        string $version,
        string $sourceRepositoryPath
    ): ?ReleasePackageEffectSet;
}
