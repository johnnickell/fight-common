<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release;

use InvalidArgumentException;

/**
 * Class ReleasePreparationArtifactFactory
 *
 * Defines canonical preparation evidence and phase-handoff payloads before identity is attached.
 */
final readonly class ReleasePreparationArtifactFactory
{
    public const string ACTIVATION_PROJECTION_BOUND = 'projection_bound';
    public const string ACTIVATION_EVIDENCE_ONLY = 'evidence_only';

    /**
     * Builds one preparation evidence-manifest identity payload
     *
     * @param string                     $planId         Immutable plan identity.
     * @param string                     $runId          Named run identity.
     * @param array<string, mixed>       $plan           Revalidated immutable plan.
     * @param string                     $status         Preparation state or stop classification.
     * @param array                      $postconditions Verified preparation postconditions.
     * @param array<string, string>      $evidence       Verified run-state digests.
     * @param array<string, string>|null $stopState      Classified stop when preparation cannot advance.
     * @param array{action: string}      $nextAction     Sole permitted next action.
     * @param string                     $activationMode Closed activation mode.
     *
     * @return array<string, mixed>
     *
     * @phpstan-param list<string> $postconditions
     */
    public function manifest(
        string $planId,
        string $runId,
        array $plan,
        string $status,
        array $postconditions,
        array $evidence,
        ?array $stopState,
        array $nextAction,
        string $activationMode = self::ACTIVATION_PROJECTION_BOUND
    ): array {
        $artifact = [
            'approvals'         => [
                'release'  => $plan['release_approval_authority'],
                'required' => $plan['required_approvals']
            ],
            'bindings'          => [
                'approved_version'            => $plan['approved_version'],
                'baseline'                    => [
                    'version'           => $plan['baseline']['version'],
                    'peeled_commit_oid' => $plan['baseline']['peeled_commit_oid'],
                    'tag_name'          => $plan['baseline']['tag_name'],
                    'tag_object_oid'    => $plan['baseline']['tag_object_oid']
                ],
                'compatibility_exceptions'    => $plan['compatibility_exceptions'],
                'evidence_manifest_digest'    => $plan['evidence_manifest_digest'],
                'evidence_requirements'       => $plan['evidence_requirements'],
                'expected_effect_classes'     => $plan['expected_effect_classes'],
                'minimum_release_class'       => $plan['minimum_release_class'],
                'patch_exception_authorities' => $plan['patch_exception_authorities'],
                'release_class'               => $plan['release_class'],
                'source_commit_oid'           => $plan['source_commit_oid'],
                'support_policy_identity'     => $plan['support_policy_identity']
            ],
            'next_action'       => $nextAction,
            'phase'             => 'preparation',
            'plan_id'           => $planId,
            'run_id'            => $runId,
            'schema_version'    => 'fight-common.release-evidence-manifest/v1',
            'status'            => $status,
            'stop_state'        => $stopState,
            'verified_evidence' => $this->verifiedEvidence($evidence, $postconditions)
        ];

        $artifact['activation'] = match ($activationMode) {
            self::ACTIVATION_PROJECTION_BOUND => [
                'mode'                              => self::ACTIVATION_PROJECTION_BOUND,
                'projection_must_bind_artifact_ids' => true,
                'required_projection_state'         => $status
            ],
            self::ACTIVATION_EVIDENCE_ONLY => [
                'mode'                              => self::ACTIVATION_EVIDENCE_ONLY,
                'projection_must_bind_artifact_ids' => false
            ],
            default => throw new InvalidArgumentException('Unknown preparation artifact activation mode.')
        };

        return $artifact;
    }

    /**
     * Builds one preparation phase-handoff identity payload
     *
     * @param array<string, mixed> $manifest  Evidence manifest without its identity.
     * @param string               $manifestId Content identity of the evidence manifest.
     *
     * @return array<string, mixed>
     */
    public function handoff(array $manifest, string $manifestId): array
    {
        $handoff = [
            'approvals'         => $manifest['approvals'],
            'bindings'          => [
                ...$manifest['bindings'],
                'evidence_manifest_id' => $manifestId
            ],
            'next_action'       => $manifest['next_action'],
            'phase'             => $manifest['phase'],
            'plan_id'           => $manifest['plan_id'],
            'run_id'            => $manifest['run_id'],
            'schema_version'    => 'fight-common.release-phase-handoff/v1',
            'status'            => $manifest['status'],
            'stop_state'        => $manifest['stop_state'],
            'verified_evidence' => $manifest['verified_evidence']
        ];

        if (isset($manifest['activation'])) {
            $handoff['activation'] = $manifest['activation'];
        }

        return $handoff;
    }

    /**
     * Returns verified evidence as canonical public artifact data
     *
     * @param array<string, string> $evidence       Verified run-state digests.
     * @param array                 $postconditions Verified postconditions.
     *
     * @return array<string, mixed>
     *
     * @phpstan-param list<string> $postconditions
     */
    private function verifiedEvidence(array $evidence, array $postconditions): array
    {
        $verified = [];

        if (isset($evidence['history_sha256'])) {
            $verified['history_sha256'] = $evidence['history_sha256'];
        }

        $verified['postconditions'] = $postconditions;

        if (isset($evidence['projection_sha256'])) {
            $verified['projection_sha256'] = $evidence['projection_sha256'];
        }

        return $verified;
    }
}
