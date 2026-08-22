<?php

declare(strict_types=1);

namespace Fight\Release\Adapter;

use Closure;
use Fight\Release\Application\Boundary\PlanArtifactStore;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffect;
use Fight\Release\Application\Boundary\ReleasePlanAuthorityPort;
use Fight\Release\Application\Boundary\ReleasePlanAuthorityStatus;
use Fight\Release\Application\CanonicalJson;
use Fight\Release\Application\ReleasePlanFactory;
use JsonException;

/**
 * Class ArtifactReleasePlanAuthority
 *
 * Revalidates mutable plan authority from one explicit local artifact.
 */
final readonly class ArtifactReleasePlanAuthority implements ReleasePlanAuthorityPort
{
    /**
     * Constructs ArtifactReleasePlanAuthority
     *
     * @param PlanArtifactStore $artifacts Artifact-store boundary.
     * @param string $authorityPath Current authority artifact path.
     * @param string $runsRoot Canonical runs root.
     * @param Closure $record Effect recorder.
     *
     * @phpstan-param Closure(ReleaseEffect, ReleaseBoundaryOutcome): void $record
     */
    public function __construct(
        private PlanArtifactStore $artifacts,
        private string $authorityPath,
        private string $runsRoot,
        private Closure $record,
        private CanonicalJson $json = new CanonicalJson(),
        private ReleasePlanFactory $plans = new ReleasePlanFactory()
    ) {
    }

    /**
     * Checks all mutable policy, evidence, compatibility, and approval bindings
     */
    public function revalidatePlanAuthority(array $plan): ReleasePlanAuthorityStatus
    {
        $output = dirname($this->authorityPath);
        $filename = basename($this->authorityPath);
        $resolution = $this->artifacts->resolveRunsDirectory($output, $this->runsRoot);

        if (
            $resolution->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || !$resolution->hasDirectory()
            || $resolution->directory?->artifactPath($filename) !== $this->authorityPath
        ) {
            if ($resolution->outcome !== ReleaseBoundaryOutcome::SUCCESS) {
                return $this->boundaryStop($resolution->outcome);
            }

            return $this->result(ReleasePlanAuthorityStatus::UNCERTAIN, ReleaseBoundaryOutcome::UNCERTAINTY);
        }

        $artifact = $this->artifacts->readArtifact($resolution->directory, $filename);

        if ($artifact->outcome !== ReleaseBoundaryOutcome::SUCCESS || $artifact->missing || !$artifact->hasContent()) {
            if ($artifact->outcome !== ReleaseBoundaryOutcome::SUCCESS) {
                return $this->boundaryStop($artifact->outcome);
            }

            return $this->result(ReleasePlanAuthorityStatus::UNCERTAIN, ReleaseBoundaryOutcome::UNCERTAINTY);
        }

        $storedContents = $artifact->contents ?? '';

        if (!str_ends_with($storedContents, "\n") || str_ends_with($storedContents, "\r\n")) {
            return $this->result(ReleasePlanAuthorityStatus::UNCERTAIN, ReleaseBoundaryOutcome::UNCERTAINTY);
        }

        $contents = substr($storedContents, 0, -1);

        try {
            $current = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $current = null;
        }

        $fields = [
            'compatibility_exceptions',
            'evidence_manifest_digest',
            'patch_exception_authorities',
            'release_approval_authority',
            'required_approvals',
            'schema_version',
            'support_policy_identity'
        ];
        $keys = is_array($current) && !array_is_list($current) ? array_keys($current) : [];
        sort($keys, SORT_STRING);

        if (
            $keys !== $fields
            || $current['schema_version'] !== 'fight-common.release-plan-authority/v1'
            || $this->json->encode($current)."\n" !== $storedContents
        ) {
            return $this->result(ReleasePlanAuthorityStatus::UNCERTAIN, ReleaseBoundaryOutcome::UNCERTAINTY);
        }

        $candidate = $plan;
        unset($candidate['plan_id']);
        $candidate['release_class'] = $candidate['minimum_release_class'] ?? null;
        unset($candidate['minimum_release_class']);
        $expected = $this->plans->create($candidate);

        foreach ($fields as $field) {
            if ($field !== 'schema_version') {
                $candidate[$field] = $current[$field];
            }
        }

        $normalized = $this->plans->create($candidate);

        if ($expected === null || $normalized === null) {
            return $this->result(ReleasePlanAuthorityStatus::UNCERTAIN, ReleaseBoundaryOutcome::UNCERTAINTY);
        }

        $status = match (true) {
            $normalized['support_policy_identity'] !== $expected['support_policy_identity'] =>
                ReleasePlanAuthorityStatus::SUPPORT_POLICY_DRIFT,
            $normalized['evidence_manifest_digest'] !== $expected['evidence_manifest_digest'] =>
                ReleasePlanAuthorityStatus::EVIDENCE_DRIFT,
            $normalized['compatibility_exceptions'] !== $expected['compatibility_exceptions'],
            $normalized['patch_exception_authorities'] !== $expected['patch_exception_authorities'] =>
                ReleasePlanAuthorityStatus::COMPATIBILITY_DRIFT,
            $normalized['required_approvals'] !== $expected['required_approvals'],
            $normalized['release_approval_authority'] !== $expected['release_approval_authority'] =>
                ReleasePlanAuthorityStatus::APPROVAL_DRIFT,
            default => ReleasePlanAuthorityStatus::VERIFIED
        };

        return $this->result($status, ReleaseBoundaryOutcome::SUCCESS);
    }

    /**
     * Records the observed authorization boundary outcome
     */
    private function result(
        ReleasePlanAuthorityStatus $status,
        ReleaseBoundaryOutcome $outcome
    ): ReleasePlanAuthorityStatus {
        ($this->record)(ReleaseEffect::AUTHORIZATION_CHECK, $outcome);

        return $status;
    }

    /**
     * Returns an exact governed authority-boundary stop classification
     */
    private function boundaryStop(ReleaseBoundaryOutcome $outcome): ReleasePlanAuthorityStatus
    {
        $status = match ($outcome) {
            ReleaseBoundaryOutcome::REFUSAL => ReleasePlanAuthorityStatus::REFUSED,
            ReleaseBoundaryOutcome::FAILURE => ReleasePlanAuthorityStatus::FAILED,
            default => ReleasePlanAuthorityStatus::UNCERTAIN
        };

        return $this->result($status, $outcome);
    }
}
