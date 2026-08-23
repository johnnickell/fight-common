<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\CanonicalRunsDirectory;
use Fight\Release\Application\Boundary\HashingPort;
use Fight\Release\Application\Boundary\PlanArtifactStore;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffectLedger;
use Fight\Release\Application\Boundary\RunStateStore;
use Fight\Release\Application\Boundary\Sha256Digest;
use JsonException;

/**
 * Class ReleaseCertificationService
 *
 * Revalidates one immutable package handoff and records its certification identity.
 */
final readonly class ReleaseCertificationService
{
    /**
     * Constructs ReleaseCertificationService
     */
    public function __construct(
        private PlanArtifactStore $artifacts,
        private HashingPort $hashing,
        private ReleaseEffectLedger $effects,
        private RunStateStore $runs,
        private CanonicalJson $json,
        private ReleaseResultFactory $results
    ) {
    }

    /**
     * Creates an immutable certification manifest after revalidating a package handoff
     */
    public function certify(string $handoffPath, string $evidencePath, string $runsDirectory): MachineResult
    {
        $directory = dirname($handoffPath);
        $filename = basename($handoffPath);
        $resolved = $this->artifacts->resolveRunsDirectory($directory, $runsDirectory);

        if (
            $resolved->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || !$resolved->hasDirectory()
            || !$resolved->directory instanceof CanonicalRunsDirectory
            || !$resolved->directory->matches($directory, $runsDirectory)
            || $resolved->directory->artifactPath($filename) !== $handoffPath
        ) {
            return $this->invalidHandoff();
        }

        $read = $this->artifacts->readArtifact($resolved->directory, $filename);

        if ($read->outcome !== ReleaseBoundaryOutcome::SUCCESS || $read->missing || !is_string($read->contents)) {
            return $this->invalidHandoff();
        }

        $bytes = $read->contents;
        if (!str_ends_with($bytes, "\n") || str_ends_with($bytes, "\r\n")) {
            return $this->invalidHandoff();
        }

        try {
            $handoff = json_decode(substr($bytes, 0, -1), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->invalidHandoff();
        }

        if (!is_array($handoff) || array_is_list($handoff) || !$this->hasValidHandoffShape($handoff)) {
            return $this->invalidHandoff();
        }

        $identityPayload = $handoff;
        unset($identityPayload['handoff_id']);
        $hash = $this->hashing->sha256($this->json->encode($identityPayload));
        $handoffId = $hash->value;

        if (
            $hash->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || !is_string($handoffId)
            || !Sha256Digest::tryFrom($handoffId) instanceof Sha256Digest
            || $handoff['handoff_id'] !== $handoffId
            || $filename !== $handoffId.'.certification-handoff.json'
        ) {
            return $this->invalidHandoff();
        }

        $evidence = $this->readEvidence($evidencePath, $resolved->directory, $identityPayload, $handoffId);

        if ($evidence === null) {
            return $this->invalidHandoff();
        }

        $laneStop = $this->laneStop($evidence['payload']);

        if ($laneStop !== null) {
            return $this->persistStop(
                $resolved->directory,
                $handoff,
                $handoffId,
                $laneStop['state'],
                $laneStop['lane'],
                $laneStop['outcome']
            );
        }

        $manifest = new ReleaseCertificationArtifactFactory()->manifest(
            $identityPayload,
            $evidence['payload'],
            $handoffId,
            $evidence['id']
        );
        $manifestHash = $this->hashing->sha256($this->json->encode($manifest));

        if ($manifestHash->outcome !== ReleaseBoundaryOutcome::SUCCESS || !is_string($manifestHash->value)) {
            return $this->invalidHandoff();
        }

        $manifestId = $manifestHash->value;
        $manifestFilename = $manifestId.'.certification-manifest.json';
        $manifestBytes = $this->json->encode([...$manifest, 'manifest_id' => $manifestId]).PHP_EOL;
        $write = $this->artifacts->writeArtifact($resolved->directory, $manifestFilename, $manifestBytes);

        if ($write->outcome !== ReleaseBoundaryOutcome::SUCCESS && !$write->requiresPostconditionVerification()) {
            return $this->invalidHandoff();
        }

        $persisted = $this->artifacts->readArtifact($resolved->directory, $manifestFilename);
        if (
            $persisted->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || $persisted->missing
            || $persisted->contents !== $manifestBytes
        ) {
            return $this->invalidHandoff();
        }

        $state = $this->runs->publishCertificationRun(
            $resolved->directory,
            $handoff['plan_id'],
            $handoff['run_id'],
            'certified',
            $manifestId,
            $handoffId,
            3,
            'prepared'
        );

        if ($state['status'] !== 'created') {
            return $this->certificationStateIndeterminate();
        }

        return $this->results->certified(
            $handoff['plan_id'],
            $handoff['run_id'],
            $state,
            ['certification_manifest' => [
                'manifest_id' => $manifestId,
                'path'        => $resolved->directory->artifactPath($manifestFilename)
            ]],
            $this->effects->effects()
        );
    }

    /**
     * Validates every certification binding before it can enter the manifest
     *
     * @param array<string, mixed> $handoff
     */
    private function hasValidHandoffShape(array $handoff): bool
    {
        $bindings = $handoff['bindings'] ?? null;
        $baseline = is_array($bindings) ? ($bindings['baseline'] ?? null) : null;
        $approvals = $handoff['approvals'] ?? null;

        return array_keys($handoff) === [
            'approvals', 'bindings', 'handoff_id', 'phase', 'plan_id', 'prepared_handoff_id', 'run_id',
            'schema_version', 'status'
        ]
            && $handoff['schema_version'] === 'fight-common.release-certification-handoff/v1'
            && $handoff['phase'] === 'certification'
            && $handoff['status'] === 'packaged'
            && $this->isDigest($handoff['plan_id'])
            && $this->isDigest($handoff['run_id'])
            && $this->isDigest($handoff['prepared_handoff_id'])
            && $this->isDigest($handoff['handoff_id'])
            && is_array($approvals)
            && array_keys($approvals) === ['release', 'required']
            && is_array($bindings)
            && array_keys($bindings) === [
                'approved_version', 'archive_digest', 'baseline', 'candidate_oid', 'evidence_manifest_digest',
                'package_effect_set_id'
            ]
            && is_string($bindings['approved_version'])
            && StableSemVer::isValid($bindings['approved_version'])
            && $this->isDigest($bindings['archive_digest'])
            && is_string($bindings['candidate_oid'])
            && preg_match('/\A[0-9a-f]{40,64}\z/D', $bindings['candidate_oid']) === 1
            && $this->isDigest($bindings['evidence_manifest_digest'])
            && $this->isDigest($bindings['package_effect_set_id'])
            && is_array($baseline)
            && array_keys($baseline) === ['peeled_commit_oid', 'tag_name', 'tag_object_oid', 'version']
            && is_string($baseline['version'])
            && StableSemVer::isValid($baseline['version'])
            && is_string($baseline['tag_name'])
            && $this->isDigestLikeGitOid($baseline['peeled_commit_oid'])
            && $this->isDigestLikeGitOid($baseline['tag_object_oid']);
    }

    /**
     * Reads and validates the content-addressed post-package evidence input
     *
     * @param string $path Evidence artifact path.
     * @param CanonicalRunsDirectory $directory Canonical artifact directory.
     * @param array<string, mixed> $handoff
     * @param string $handoffId Certification handoff identity.
     *
     * @return array{id: string, payload: array<string, mixed>}|null
     */
    private function readEvidence(
        string $path,
        CanonicalRunsDirectory $directory,
        array $handoff,
        string $handoffId
    ): ?array {
        $filename = basename($path);

        if ($directory->artifactPath($filename) !== $path) {
            return null;
        }

        $read = $this->artifacts->readArtifact($directory, $filename);

        if ($read->outcome !== ReleaseBoundaryOutcome::SUCCESS || $read->missing || !is_string($read->contents)) {
            return null;
        }

        try {
            $payload = json_decode(rtrim($read->contents, "\n"), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($payload) || array_is_list($payload) || !is_string($payload['evidence_id'] ?? null)) {
            return null;
        }

        $id = $payload['evidence_id'];
        unset($payload['evidence_id']);
        $hash = $this->hashing->sha256($this->json->encode($payload));

        if (
            $hash->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || $hash->value !== $id
            || $filename !== $id.'.certification-evidence.json'
            || !$this->hasValidEvidence($payload, $handoff, $handoffId)
        ) {
            return null;
        }

        return ['id' => $id, 'payload' => $payload];
    }

    /**
     * Validates the complete certification evidence payload
     *
     * @param array<string, mixed> $evidence
     * @param array<string, mixed> $handoff
     */
    private function hasValidEvidence(array $evidence, array $handoff, string $handoffId): bool
    {
        $categories = [
            'structural-api',
            'compatibility-manifest',
            'composer-constraints',
            'package-surface',
            'archive-contents',
            'behavioral-fixtures',
            'serialization-fixtures',
            'persistence-fixtures',
            'adapter-fixtures',
            'dependency-lowest',
            'dependency-locked',
            'dependency-latest',
            'static-analysis',
            'deprecation-discipline'
        ];
        $lanes = [
            'archive_install',
            'compatibility_git_ref',
            'dependency_latest',
            'dependency_locked',
            'dependency_lowest',
            'planning_api',
            'quality'
        ];

        if (
            array_keys($evidence) !== [
                'bindings', 'certification_handoff_id', 'classification_records', 'lanes', 'schema_version'
            ]
            || $evidence['schema_version'] !== 'fight-common.release-certification-evidence/v1'
            || $evidence['certification_handoff_id'] !== $handoffId
            || $evidence['bindings'] !== $handoff['bindings']
            || !is_array($evidence['classification_records'])
            || !$this->hasExactKeys($evidence['classification_records'], $categories)
            || !is_array($evidence['lanes']) || array_keys($evidence['lanes']) !== $lanes
        ) {
            return false;
        }

        foreach ($categories as $category) {
            $record = $evidence['classification_records'][$category];
            if (
                !is_array($record)
                || array_keys($record) !== ['category', 'classification', 'evidence_id', 'finding_id']
                || $record['category'] !== $category
                || !is_string($record['finding_id'])
                || $record['finding_id'] === ''
                || !is_string($record['evidence_id']) || $record['evidence_id'] === ''
                || !in_array($record['classification'], ['patch', 'minor', 'major', 'indeterminate'], true)
            ) {
                return false;
            }
        }

        foreach ($lanes as $lane) {
            $value = $evidence['lanes'][$lane];
            if (!is_array($value) || array_is_list($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates an array's keys against one required key set
     *
     * @param array<string, mixed> $value
     * @param array $required
     *
     * @phpstan-param list<string> $required
     */
    private function hasExactKeys(array $value, array $required): bool
    {
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($required, SORT_STRING);

        return $actual === $required;
    }

    /**
     * Reports the first durable stop required by a complete lane set
     *
     * @param array<string, mixed> $handoff
     *
     * @return array{state: string, lane: string, outcome: string}|null
     */
    private function laneStop(array $handoff): ?array
    {
        $required = [
            'archive_install', 'compatibility_git_ref', 'dependency_latest', 'dependency_locked',
            'dependency_lowest', 'planning_api', 'quality'
        ];

        foreach ($required as $name) {
            $lane = $handoff['lanes'][$name] ?? null;
            if (is_array($lane) && ($lane['outcome'] ?? null) === 'failed') {
                return ['state' => 'certification_failed', 'lane' => $name, 'outcome' => 'failed'];
            }
        }

        foreach ($required as $name) {
            $lane = $handoff['lanes'][$name] ?? null;
            if (!is_array($lane) || ($lane['outcome'] ?? null) !== 'verified') {
                return [
                    'state'   => 'evidence_indeterminate',
                    'lane'    => $name,
                    'outcome' => is_array($lane) && is_string($lane['outcome'] ?? null) ? $lane['outcome'] : 'missing'
                ];
            }
        }

        return null;
    }

    /**
     * Persists and returns one fail-closed stop without creating a certification manifest
     *
     * @param CanonicalRunsDirectory $directory Canonical artifact directory.
     * @param array<string, mixed> $handoff
     * @param string $handoffId Certification handoff identity.
     * @param string $state Certification outcome state.
     * @param string $lane Certification lane name.
     * @param string $outcome Certification lane outcome.
     */
    private function persistStop(
        CanonicalRunsDirectory $directory,
        array $handoff,
        string $handoffId,
        string $state,
        string $lane,
        string $outcome
    ): MachineResult {
        $stop = new ReleaseCertificationArtifactFactory()->stop($handoff, $handoffId, $state, $lane, $outcome);
        $hash = $this->hashing->sha256($this->json->encode($stop));

        if ($hash->outcome !== ReleaseBoundaryOutcome::SUCCESS || !is_string($hash->value)) {
            return $this->invalidHandoff();
        }

        $stopId = $hash->value;
        $filename = $stopId.'.certification-stop.json';
        $bytes = $this->json->encode([...$stop, 'stop_id' => $stopId]).PHP_EOL;
        $write = $this->artifacts->writeArtifact($directory, $filename, $bytes);
        $persisted = $this->artifacts->readArtifact($directory, $filename);

        if (
            ($write->outcome !== ReleaseBoundaryOutcome::SUCCESS && !$write->requiresPostconditionVerification())
            || $persisted->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || $persisted->missing
            || $persisted->contents !== $bytes
        ) {
            return $this->invalidHandoff();
        }

        $runState = $this->runs->publishCertificationRun(
            $directory,
            $handoff['plan_id'],
            $handoff['run_id'],
            $state,
            $stopId,
            $handoffId,
            3,
            'prepared'
        );

        if ($runState['status'] !== 'created') {
            return $this->certificationStateIndeterminate();
        }

        return $this->results->certificationStop(
            $handoff['plan_id'],
            $handoff['run_id'],
            $state,
            $lane,
            $runState,
            ['certification_stop' => [
                'stop_id' => $stopId,
                'path'    => $directory->artifactPath($filename)
            ]],
            $this->effects->effects()
        );
    }

    /**
     * Reports whether the value is one SHA-256 content identity
     */
    private function isDigest(mixed $value): bool
    {
        return is_string($value) && Sha256Digest::tryFrom($value) instanceof Sha256Digest;
    }

    /**
     * Reports whether the value is one exact Git object identity
     */
    private function isDigestLikeGitOid(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[0-9a-f]{40,64}\z/D', $value) === 1;
    }

    /**
     * Builds the sole invalid package-handoff result
     */
    private function invalidHandoff(): MachineResult
    {
        return $this->results->failure(
            'certify',
            'release.certification.handoff_invalid',
            'Certification requires one canonical package handoff below the repository .runs directory.',
            'select_verified_package_handoff',
            $this->effects->effects()
        );
    }

    /**
     * Builds the fail-closed result when certification state cannot be confirmed durable
     */
    private function certificationStateIndeterminate(): MachineResult
    {
        return $this->results->failure(
            'certify',
            'release.certification.state_persistence_indeterminate',
            'The certification artifact was written but its release-run state could not be confirmed durable.',
            'reconcile_named_release_run',
            $this->effects->effects()
        );
    }
}
