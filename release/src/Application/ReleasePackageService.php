<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\ArchivePort;
use Fight\Release\Application\Boundary\CanonicalRunsDirectory;
use Fight\Release\Application\Boundary\FilesystemPort;
use Fight\Release\Application\Boundary\HashingPort;
use Fight\Release\Application\Boundary\PlanArtifactStore;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffectLedger;
use Fight\Release\Application\Boundary\ReleasePackageEffectSet;
use Fight\Release\Application\Boundary\ScopedReleaseEffectLedger;
use Fight\Release\Application\Boundary\Sha256Digest;
use JsonException;

/**
 * Class ReleasePackageService
 *
 * Creates one deterministic rootless archive from a prepared release handoff.
 */
final readonly class ReleasePackageService
{
    /**
     * Constructs ReleasePackageService
     */
    public function __construct(
        private PlanArtifactStore $artifacts,
        private ArchivePort $archive,
        private FilesystemPort $filesystem,
        private HashingPort $hashing,
        private ReleaseEffectLedger $effects,
        private CanonicalJson $json,
        private ReleaseResultFactory $results
    ) {
    }

    /**
     * Creates one deterministic archive after revalidating its prepared handoff
     */
    public function package(string $handoffPath, string $runsDirectory, ?string $approvalPath = null): MachineResult
    {
        if ($this->effects instanceof ScopedReleaseEffectLedger) {
            $this->effects->beginEffectScope();
        }

        $output = dirname($handoffPath);
        $filename = basename($handoffPath);
        $resolved = $this->artifacts->resolveRunsDirectory($output, $runsDirectory);

        if (
            $resolved->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || !$resolved->hasDirectory()
            || !$resolved->directory instanceof CanonicalRunsDirectory
            || !$resolved->directory->matches($output, $runsDirectory)
            || $resolved->directory->artifactPath($filename) !== $handoffPath
        ) {
            return $this->results->failure(
                'package',
                'release.package.handoff_forbidden',
                'Packaging requires one phase handoff below the repository .runs directory.',
                'select_immutable_phase_handoff',
                $this->performedEffects()
            );
        }

        $artifact = $this->artifacts->readArtifact($resolved->directory, $filename);

        if ($artifact->outcome !== ReleaseBoundaryOutcome::SUCCESS || $artifact->missing || !$artifact->hasContent()) {
            return $this->results->failure(
                'package',
                'release.package.handoff_unreadable',
                'The phase handoff could not be read.',
                'select_immutable_phase_handoff',
                $this->performedEffects()
            );
        }

        $handoffBytes = $artifact->contents ?? '';

        if (!str_ends_with($handoffBytes, "\n") || str_ends_with($handoffBytes, "\r\n")) {
            return $this->invalidHandoff($this->performedEffects());
        }

        $contents = substr($handoffBytes, 0, -1);

        try {
            $handoff = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $handoff = null;
        }

        if (
            !is_array($handoff)
            || array_is_list($handoff)
            || ($handoff['schema_version'] ?? null) !== 'fight-common.release-phase-handoff/v1'
            || ($handoff['status'] ?? null) !== 'prepared'
        ) {
            return $this->invalidHandoff($this->performedEffects());
        }

        $planId = $handoff['plan_id'] ?? null;
        $runId = $handoff['run_id'] ?? null;

        if (
            !is_string($planId)
            || !is_string($runId)
            || !Sha256Digest::tryFrom($planId) instanceof Sha256Digest
            || !Sha256Digest::tryFrom($runId) instanceof Sha256Digest
            || !str_ends_with($filename, '.phase-handoff.json')
        ) {
            return $this->invalidHandoff($this->performedEffects());
        }

        $handoffIdentity = null;
        $candidate = $handoff;
        unset($candidate['handoff_id']);
        $reencoded = $this->json->encode($candidate);

        $hash = $this->hashing->sha256($reencoded);

        if ($hash->outcome !== ReleaseBoundaryOutcome::SUCCESS || !is_string($hash->value)) {
            return $this->invalidHandoff($this->performedEffects());
        }

        $handoffIdentity = $hash->value;

        if (str_starts_with($filename, $handoffIdentity)) {
            $prefix = $handoffIdentity;
        } else {
            $prefix = substr($filename, 0, 64);
        }

        if (!Sha256Digest::tryFrom($prefix) instanceof Sha256Digest) {
            return $this->invalidHandoff($this->performedEffects());
        }

        if (($handoff['handoff_id'] ?? null) !== $handoffIdentity || $prefix !== $handoffIdentity) {
            return $this->invalidHandoff($this->performedEffects());
        }

        $bindings = $handoff['bindings'] ?? [];
        $sourceCommitOid = $bindings['source_commit_oid'] ?? null;
        $approvedVersion = $bindings['approved_version'] ?? null;

        if (
            !is_string($sourceCommitOid)
            || $sourceCommitOid === ''
            || !is_string($approvedVersion)
            || $approvedVersion === ''
        ) {
            return $this->invalidHandoff($this->performedEffects());
        }

        $effectSet = $this->archive->deriveEffectSet(
            $sourceCommitOid,
            $approvedVersion,
            dirname(__DIR__, 3)
        );

        if (!$effectSet instanceof ReleasePackageEffectSet) {
            return $this->results->failure(
                'package',
                'release.package.effect_set_derivation_failed',
                'The archive effect set could not be derived from the candidate commit.',
                'repair_release_repository_storage',
                $this->performedEffects()
            );
        }

        if ($approvalPath !== null) {
            $approval = $this->filesystem->read($approvalPath);

            if ($approval->outcome !== ReleaseBoundaryOutcome::SUCCESS || !$approval->hasValue()) {
                return $this->results->failure(
                    'package',
                    'release.package.approval_unreadable',
                    'The package approval could not be read.',
                    'provide_valid_package_approval',
                    $this->performedEffects()
                );
            }

            try {
                $approvedSet = json_decode($approval->value, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->results->failure(
                    'package',
                    'release.package.approval_invalid',
                    'The package approval must be valid JSON.',
                    'provide_valid_package_approval',
                    $this->performedEffects()
                );
            }

            $approvedEffectSetId = is_array($approvedSet) ? ($approvedSet['effect_set_id'] ?? null) : null;

            if (
                !is_string($approvedEffectSetId)
                || $approvedEffectSetId === ''
                || $approvedEffectSetId !== $effectSet->effectSetId
            ) {
                return $this->results->packageRefusal(
                    $planId,
                    $runId,
                    $this->performedEffects()
                );
            }
        }

        $archiveResult = $this->archive->createArchive(
            $sourceCommitOid,
            $approvedVersion,
            dirname(__DIR__, 3),
            $effectSet->excludedPaths
        );

        if (
            !$archiveResult->hasArchive()
            || $archiveResult->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || !is_string($archiveResult->sha256Digest)
        ) {
            if ($archiveResult->outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED) {
                $artifacts = $this->publishCertificationHandoff(
                    $resolved->directory,
                    $handoff,
                    $handoffIdentity,
                    $archiveResult->sha256Digest ?? '',
                    $effectSet
                );

                if ($artifacts === null) {
                    return $this->invalidHandoff($this->performedEffects());
                }

                return $this->results->packageAlreadySatisfied(
                    $planId,
                    $runId,
                    $sourceCommitOid,
                    $archiveResult->sha256Digest ?? '',
                    $effectSet,
                    $artifacts,
                    $this->performedEffects()
                );
            }

            /** @phpstan-ignore match.unhandled (success is guarded by the completed-archive check above) */
            $stop = match ($archiveResult->outcome) {
                ReleaseBoundaryOutcome::REFUSAL => 'archive_refused',
                ReleaseBoundaryOutcome::FAILURE => 'archive_failed',
                ReleaseBoundaryOutcome::UNCERTAINTY => 'archive_uncertain',
                ReleaseBoundaryOutcome::DRIFT => 'archive_drift'
            };

            return $this->results->packageArchiveStop(
                $stop,
                $planId,
                $runId,
                $this->performedEffects()
            );
        }

        $artifacts = $this->publishCertificationHandoff(
            $resolved->directory,
            $handoff,
            $handoffIdentity,
            $archiveResult->sha256Digest,
            $effectSet
        );

        if ($artifacts === null) {
            return $this->invalidHandoff($this->performedEffects());
        }

        return $this->results->packaged(
            $planId,
            $runId,
            $sourceCommitOid,
            $archiveResult->sha256Digest,
            $effectSet,
            $artifacts,
            $this->performedEffects()
        );
    }

    /**
     * Persists and independently rereads the sole certification input artifact
     *
     * @param CanonicalRunsDirectory $directory
     * @param array<string, mixed> $preparedHandoff
     *
     * @return array{certification_handoff: array{handoff_id: string, path: string}}|null
     */
    private function publishCertificationHandoff(
        CanonicalRunsDirectory $directory,
        array $preparedHandoff,
        string $preparedHandoffId,
        string $archiveDigest,
        ReleasePackageEffectSet $effectSet
    ): ?array {
        $handoff = new ReleaseCertificationArtifactFactory()->handoff(
            $preparedHandoff,
            $preparedHandoffId,
            $archiveDigest,
            $effectSet->effectSetId
        );
        $encoded = $this->json->encode($handoff);
        $hash = $this->hashing->sha256($encoded);

        if ($hash->outcome !== ReleaseBoundaryOutcome::SUCCESS || !is_string($hash->value)) {
            return null;
        }

        $handoffId = $hash->value;
        $filename = $handoffId.'.certification-handoff.json';
        $bytes = $this->json->encode([...$handoff, 'handoff_id' => $handoffId]).PHP_EOL;
        $write = $this->artifacts->writeArtifact($directory, $filename, $bytes);

        if ($write->outcome !== ReleaseBoundaryOutcome::SUCCESS && !$write->requiresPostconditionVerification()) {
            return null;
        }

        $read = $this->artifacts->readArtifact($directory, $filename);

        if ($read->outcome !== ReleaseBoundaryOutcome::SUCCESS || $read->missing || $read->contents !== $bytes) {
            return null;
        }

        return ['certification_handoff' => [
            'handoff_id' => $handoffId,
            'path'       => $directory->artifactPath($filename)
        ]];
    }

    /**
     * Returns the performed-effect projection for the current invocation
     *
     * @return list<array{capability: string, effect_class: string, outcome: string}>
     */
    private function performedEffects(): array
    {
        return $this->effects->effects();
    }

    /**
     * Builds one canonical invalid-handoff stop
     *
     * @param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    private function invalidHandoff(array $performedEffects): MachineResult
    {
        return $this->results->failure(
            'package',
            'release.package.handoff_invalid',
            'The phase handoff failed canonical identity or binding revalidation.',
            'create_current_release_plan',
            $performedEffects
        );
    }
}
