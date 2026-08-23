<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class ReleaseCertificationArtifactFactory
 *
 * Defines the immutable package handoff and certification manifest payloads.
 */
final readonly class ReleaseCertificationArtifactFactory
{
    /**
     * Builds the only package artifact that certification may consume
     *
     * @param array<string, mixed> $preparedHandoff
     *
     * @return array<string, mixed>
     */
    public function handoff(
        array $preparedHandoff,
        string $preparedHandoffId,
        string $archiveDigest,
        string $effectSetId
    ): array {
        return [
            'approvals'           => $preparedHandoff['approvals'],
            'bindings'            => [
                'approved_version'         => $preparedHandoff['bindings']['approved_version'],
                'archive_digest'           => $archiveDigest,
                'baseline'                 => $preparedHandoff['bindings']['baseline'],
                'candidate_oid'            => $preparedHandoff['bindings']['source_commit_oid'],
                'evidence_manifest_digest' => $preparedHandoff['bindings']['evidence_manifest_digest'],
                'package_effect_set_id'    => $effectSetId
            ],
            'phase'               => 'certification',
            'plan_id'             => $preparedHandoff['plan_id'],
            'prepared_handoff_id' => $preparedHandoffId,
            'run_id'              => $preparedHandoff['run_id'],
            'schema_version'      => 'fight-common.release-certification-handoff/v1',
            'status'              => 'packaged'
        ];
    }

    /**
     * Builds one compact certification manifest before its content identity is attached
     *
     * @param array<string, mixed> $handoff
     * @param array<string, mixed> $evidence
     *
     * @return array<string, mixed>
     */
    public function manifest(array $handoff, array $evidence, string $handoffId, string $evidenceId): array
    {
        return [
            'approvals'                 => $handoff['approvals'],
            'bindings'                  => $handoff['bindings'],
            'certification_handoff_id'  => $handoffId,
            'certification_evidence_id' => $evidenceId,
            'classification_records'    => $evidence['classification_records'],
            'lanes'                     => [
                'archive_install'       => [
                    'archive_digest'           => $handoff['bindings']['archive_digest'],
                    'evidence_manifest_digest' => $handoff['bindings']['evidence_manifest_digest'],
                    'outcome'                  => $evidence['lanes']['archive_install']['outcome']
                ],
                'compatibility_git_ref' => [
                    'baseline'                 => $handoff['bindings']['baseline'],
                    'candidate_oid'            => $handoff['bindings']['candidate_oid'],
                    'evidence_manifest_digest' => $handoff['bindings']['evidence_manifest_digest'],
                    'outcome'                  => $evidence['lanes']['compatibility_git_ref']['outcome']
                ],
                'dependency_latest'     => [
                    'evidence_manifest_digest' => $handoff['bindings']['evidence_manifest_digest'],
                    'outcome'                  => $evidence['lanes']['dependency_latest']['outcome'],
                    'resolution'               => 'latest-permitted'
                ],
                'dependency_locked'     => [
                    'evidence_manifest_digest' => $handoff['bindings']['evidence_manifest_digest'],
                    'outcome'                  => $evidence['lanes']['dependency_locked']['outcome'],
                    'resolution'               => 'repository-locked'
                ],
                'dependency_lowest'     => [
                    'evidence_manifest_digest' => $handoff['bindings']['evidence_manifest_digest'],
                    'outcome'                  => $evidence['lanes']['dependency_lowest']['outcome'],
                    'resolution'               => 'lowest-permitted'
                ],
                'planning_api'          => [
                    'baseline'                 => $handoff['bindings']['baseline'],
                    'evidence_manifest_digest' => $handoff['bindings']['evidence_manifest_digest'],
                    'outcome'                  => $evidence['lanes']['planning_api']['outcome']
                ],
                'quality'               => [
                    'evidence_manifest_digest' => $handoff['bindings']['evidence_manifest_digest'],
                    'outcome'                  => $evidence['lanes']['quality']['outcome']
                ]
            ],
            'phase'                     => 'certification',
            'plan_id'                   => $handoff['plan_id'],
            'run_id'                    => $handoff['run_id'],
            'schema_version'            => 'fight-common.release-certification-manifest/v1',
            'status'                    => 'certified'
        ];
    }

    /**
     * Builds one immutable certification stop before its content identity is attached
     *
     * @param array<string, mixed> $handoff
     *
     * @return array<string, mixed>
     */
    public function stop(
        array $handoff,
        string $handoffId,
        string $state,
        string $lane,
        string $outcome
    ): array {
        return [
            'certification_handoff_id' => $handoffId,
            'lane'                     => $lane,
            'outcome'                  => $outcome,
            'phase'                    => 'certification',
            'plan_id'                  => $handoff['plan_id'],
            'run_id'                   => $handoff['run_id'],
            'schema_version'           => 'fight-common.release-certification-stop/v1',
            'state'                    => $state
        ];
    }
}
