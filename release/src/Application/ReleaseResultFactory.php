<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffectLedger;
use Fight\Release\Application\Boundary\ReleasePackageEffectSet;
use InvalidArgumentException;

/**
 * Class ReleaseResultFactory
 *
 * Builds the stable machine-result contract for release capabilities.
 */
final readonly class ReleaseResultFactory
{
    /**
     * Constructs ReleaseResultFactory
     */
    public function __construct(private ?ReleaseEffectLedger $effects = null)
    {
    }

    /**
     * Builds the sole governed result available when the canonical runtime cannot start
     */
    public function runtimeFailure(string $requestedCommand): MachineResult
    {
        [$command, $capability] = ReleaseCommand::runtimeMetadata($requestedCommand);

        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => $command,
            'capability'              => $capability,
            'status'                  => MachineResult::RUNTIME_FAILURE_STATUS,
            'exit_class'              => 'failed',
            'findings'                => [[
                'id'      => MachineResult::RUNTIME_FAILURE_FINDING,
                'message' => MachineResult::RUNTIME_FAILURE_MESSAGE
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => ['action' => MachineResult::RUNTIME_FAILURE_ACTION]
        ], 70);
    }

    /**
     * Builds the governed result used when a started runtime cannot return an authenticated result
     */
    public function runtimeTermination(string $requestedCommand): MachineResult
    {
        [$command, $capability] = ReleaseCommand::runtimeMetadata($requestedCommand);

        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => $command,
            'capability'              => $capability,
            'status'                  => MachineResult::RUNTIME_TERMINATION_STATUS,
            'exit_class'              => 'failed',
            'findings'                => [[
                'id'      => MachineResult::RUNTIME_TERMINATION_FINDING,
                'message' => MachineResult::RUNTIME_TERMINATION_MESSAGE
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => ['action' => MachineResult::RUNTIME_TERMINATION_ACTION]
        ], 71);
    }

    /**
     * Builds an invalid-input machine result
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}>|null $performedEffects
     */
    public function failure(
        string $command,
        string $findingId,
        string $message,
        string $nextAction,
        ?array $performedEffects = null
    ): MachineResult {
        $capability = ReleaseCommand::capabilityFor($command);

        $payload = [
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => $command,
            'capability'              => $capability,
            'status'                  => 'policy_blocked',
            'exit_class'              => 'invalid_input',
            'findings'                => [[
                'id'      => $findingId,
                'message' => $message
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => $performedEffects ?? $this->effects?->effects() ?? [],
            'proposed_effects'        => [],
            'next_action'             => ['action' => $nextAction]
        ];

        return new MachineResult($payload, 2);
    }

    /**
     * Builds the successful result for one distinct prepared execution attempt
     *
     * @param string  $planId           Immutable plan identity.
     * @param string  $runId            Unique execution-attempt identity.
     * @param array   $state            Run-state artifact references.
     * @param array   $artifacts        Content-addressed evidence and handoff references.
     * @param array   $performedEffects Ordered boundary effect projection.
     *
     * @phpstan-param array{history_path: string, projection_path: string} $state
     * @phpstan-param array{
     *     evidence_manifest: array{manifest_id: string, path: string},
     *     phase_handoff: array{handoff_id: string, path: string}
     * } $artifacts
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function prepared(
        string $planId,
        string $runId,
        array $state,
        array $artifacts,
        array $performedEffects
    ): MachineResult {
        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'prepare',
            'capability'              => 'release_preparation',
            'status'                  => 'prepared',
            'exit_class'              => 'success',
            'plan_id'                 => $planId,
            'run_id'                  => $runId,
            'run_state'               => $state,
            'artifacts'               => $artifacts,
            'findings'                => [[
                'id'      => 'release.prepare.completed',
                'message' => 'The immutable plan was revalidated and a distinct prepared run was persisted.'
            ]],
            'verified_postconditions' => [
                'immutable_plan_revalidated',
                'prepared_run_projection_published'
            ],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'package_release_run']
        ], 0);
    }

    /**
     * Builds idempotent success after one named prepared run is fully reverified
     *
     * @phpstan-param array{history_path: string, projection_path: string} $state
     * @phpstan-param array{
     *     evidence_manifest: array{manifest_id: string, path: string},
     *     phase_handoff: array{handoff_id: string, path: string}
     * } $artifacts
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function resumedPrepared(
        string $planId,
        string $runId,
        array $state,
        array $artifacts,
        array $performedEffects
    ): MachineResult {
        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'prepare',
            'capability'              => 'release_preparation',
            'status'                  => 'prepared',
            'exit_class'              => 'success',
            'plan_id'                 => $planId,
            'run_id'                  => $runId,
            'run_state'               => $state,
            'artifacts'               => $artifacts,
            'findings'                => [[
                'id'      => 'release.prepare.already_satisfied',
                'message' => 'The named prepared run and every claimed postcondition were reverified.'
            ]],
            'verified_postconditions' => [
                'immutable_plan_revalidated',
                'run_event_chain_revalidated',
                'prepared_run_projection_revalidated',
                'prepared_postconditions_reverified'
            ],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'package_release_run']
        ], 0);
    }

    /**
     * Builds truthful success when a named resume completes previously unfinished preparation
     *
     * @phpstan-param array{history_path: string, projection_path: string} $state
     * @phpstan-param array{
     *     evidence_manifest: array{manifest_id: string, path: string},
     *     phase_handoff: array{handoff_id: string, path: string}
     * } $artifacts
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function resumedPreparationCompleted(
        string $planId,
        string $runId,
        array $state,
        array $artifacts,
        array $performedEffects
    ): MachineResult {
        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'prepare',
            'capability'              => 'release_preparation',
            'status'                  => 'prepared',
            'exit_class'              => 'success',
            'plan_id'                 => $planId,
            'run_id'                  => $runId,
            'run_state'               => $state,
            'artifacts'               => $artifacts,
            'findings'                => [[
                'id'      => 'release.prepare.resumed_completed',
                'message' => 'The named release run was revalidated and preparation completed during resume.'
            ]],
            'verified_postconditions' => [
                'immutable_plan_revalidated',
                'run_event_chain_revalidated',
                'prepared_run_projection_published',
                'prepared_postconditions_verified'
            ],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'package_release_run']
        ], 0);
    }

    /**
     * Builds one canonical stop for failure to revalidate a named run
     *
     * @phpstan-param array{
     *     evidence_manifest: array{manifest_id: string, path: string},
     *     phase_handoff: array{handoff_id: string, path: string}
     * }|null $artifacts
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function prepareResumeStop(
        string $stop,
        string $planId,
        string $runId,
        ?array $artifacts,
        array $performedEffects
    ): MachineResult {
        if ($artifacts === null) {
            throw new InvalidArgumentException('A normal preparation stop requires durable artifact references.');
        }

        [$status, $exitClass, $exitCode, $finding, $message, $action] = match ($stop) {
            'missing' => [
                'evidence_indeterminate',
                'uncertain',
                5,
                'release.prepare.resume_state_missing',
                'The named release run evidence is missing.',
                'restore_named_release_run_evidence'
            ],
            'conflict' => [
                'conflict',
                'refused',
                23,
                'release.prepare.resume_contention',
                'Another writer currently owns the named release run.',
                'retry_named_resume_after_writer_completes'
            ],
            'stale' => [
                'stale_plan',
                'drifted',
                6,
                'release.prepare.resume_plan_drift',
                'The named release run is bound to a different immutable plan.',
                'create_current_release_plan'
            ],
            'failed' => [
                'policy_blocked',
                'failed',
                4,
                'release.prepare.state_persistence_failed',
                'The release run could not be durably persisted.',
                'repair_release_run_storage'
            ],
            'create_conflict' => [
                'conflict',
                'refused',
                23,
                'release.prepare.run_identity_conflict',
                'The generated release run identity already exists.',
                'retry_release_preparation_with_new_run'
            ],
            'state_indeterminate' => [
                'evidence_indeterminate',
                'uncertain',
                5,
                'release.prepare.state_persistence_indeterminate',
                'The release run state may have been partially persisted.',
                'reconcile_named_release_run'
            ],
            'artifact_indeterminate' => [
                'evidence_indeterminate',
                'uncertain',
                5,
                'release.prepare.artifacts_indeterminate',
                'Preparation evidence or its handoff could not be verified.',
                'reconcile_named_release_run'
            ],
            'baseline_refusal' => [
                'authority_required',
                'refused',
                3,
                'release.prepare.baseline_resolution_refused',
                'The current baseline identity could not be resolved without additional authority.',
                'obtain_current_baseline_authority'
            ],
            'baseline_failure' => [
                'policy_blocked',
                'failed',
                4,
                'release.prepare.baseline_resolution_failed',
                'The current baseline identity provider failed during revalidation.',
                'repair_baseline_resolution_provider'
            ],
            'baseline_uncertainty' => [
                'evidence_indeterminate',
                'uncertain',
                5,
                'release.prepare.baseline_resolution_uncertain',
                'The current baseline identity could not be determined conclusively.',
                'reconcile_baseline_resolution'
            ],
            'baseline_drift' => [
                'stale_plan',
                'drifted',
                6,
                'release.prepare.baseline_resolution_drift',
                'The current baseline identity drifted from its immutable plan binding.',
                'create_current_release_plan'
            ],
            'baseline_missing' => [
                'policy_blocked',
                'failed',
                4,
                'release.prepare.baseline_tag_missing',
                'The plan baseline tag is missing from the current repository authority.',
                'repair_baseline_authority'
            ],
            'baseline_ambiguous' => [
                'policy_blocked',
                'failed',
                4,
                'release.prepare.baseline_tag_ambiguous',
                'The plan baseline tag does not resolve to one annotated tag authority.',
                'repair_baseline_authority'
            ],
            'baseline_duplicate_normalized' => [
                'policy_blocked',
                'failed',
                4,
                'release.prepare.baseline_tag_duplicate_normalized',
                'The plan baseline tag has duplicate normalized release authority.',
                'repair_baseline_authority'
            ],
            'baseline_non_ancestor' => [
                'policy_blocked',
                'failed',
                4,
                'release.prepare.baseline_tag_non_ancestor',
                'The plan baseline commit is not an ancestor of the bound source commit.',
                'repair_baseline_authority'
            ],
            'support_policy_drift' => [
                'stale_plan',
                'drifted',
                6,
                'release.prepare.support_policy_drift',
                'The plan support-policy authority no longer matches current truth.',
                'create_current_release_plan'
            ],
            'approval_drift' => [
                'authority_required',
                'refused',
                3,
                'release.prepare.approval_authority_drift',
                'The plan approval authority no longer matches current truth.',
                'obtain_current_release_approval'
            ],
            'evidence_drift' => [
                'stale_plan',
                'drifted',
                6,
                'release.prepare.evidence_authority_drift',
                'The plan evidence authority no longer matches current truth.',
                'create_current_release_plan'
            ],
            'compatibility_drift' => [
                'stale_plan',
                'drifted',
                6,
                'release.prepare.compatibility_authority_drift',
                'The plan compatibility authority no longer matches current truth.',
                'create_current_release_plan'
            ],
            'authority_refused' => [
                'authority_required',
                'refused',
                3,
                'release.prepare.plan_authority_refused',
                'The current release-plan authority refused preparation.',
                'obtain_current_release_authority'
            ],
            'authority_failed' => [
                'policy_blocked',
                'failed',
                4,
                'release.prepare.plan_authority_failed',
                'The current release-plan authority could not be revalidated.',
                'repair_release_authority_provider'
            ],
            'authority_uncertain' => [
                'evidence_indeterminate',
                'uncertain',
                5,
                'release.prepare.plan_authority_uncertain',
                'The current release-plan authority is uncertain.',
                'reconcile_release_plan_authority'
            ],
            default => [
                'evidence_indeterminate',
                'uncertain',
                5,
                'release.prepare.resume_state_indeterminate',
                'The named release run history, projection, or prepared postcondition is indeterminate.',
                'reconcile_named_release_run'
            ]
        };

        $payload = [
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'prepare',
            'capability'              => 'release_preparation',
            'status'                  => $status,
            'exit_class'              => $exitClass,
            'plan_id'                 => $planId,
            'run_id'                  => $runId,
            'findings'                => [['id' => $finding, 'message' => $message]],
            'verified_postconditions' => [],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => $action]
        ];

        $payload['artifacts'] = $artifacts;

        return new MachineResult($payload, $exitCode);
    }

    /**
     * Builds the only normal preparation stop that cannot carry durable artifact references
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function prepareEvidencePersistenceFailure(
        string $planId,
        string $runId,
        array $performedEffects
    ): MachineResult {
        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'prepare',
            'capability'              => 'release_preparation',
            'status'                  => 'evidence_indeterminate',
            'exit_class'              => 'uncertain',
            'plan_id'                 => $planId,
            'run_id'                  => $runId,
            'findings'                => [[
                'id'      => 'release.prepare.evidence_persistence_failed',
                'message' => 'Preparation evidence could not be durably persisted or reverified.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'repair_release_evidence_storage']
        ], 5);
    }

    /**
     * Builds one exact persisted evidence-exhaustion stop after revalidating its named state
     *
     * @param string $planId Immutable plan identity.
     * @param string $runId Named run identity.
     * @param array $runState Revalidated stopped state.
     * @param array $performedEffects Ordered boundary effect projection.
     *
     * @phpstan-param array{history_path: string, projection_path: string} $runState
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function resumedPrepareEvidencePersistenceFailure(
        string $planId,
        string $runId,
        array $runState,
        array $performedEffects
    ): MachineResult {
        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'prepare',
            'capability'              => 'release_preparation',
            'status'                  => 'evidence_indeterminate',
            'exit_class'              => 'uncertain',
            'plan_id'                 => $planId,
            'run_id'                  => $runId,
            'run_state'               => $runState,
            'findings'                => [[
                'id'      => 'release.prepare.evidence_persistence_failed',
                'message' => 'Preparation evidence could not be durably persisted or reverified.'
            ]],
            'verified_postconditions' => [
                'run_event_chain_revalidated',
                'stopped_run_projection_revalidated'
            ],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'repair_release_evidence_storage']
        ], 5);
    }

    /**
     * Builds one detailed invalid-plan result while retaining the stable coarse class
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function planValidationFailure(
        ReleasePlanValidationFailure $failure,
        array $performedEffects = []
    ): MachineResult {
        return $this->failure(
            'plan',
            $failure->findingId(),
            $failure->message(),
            $failure->nextAction(),
            $performedEffects
        );
    }

    /**
     * Builds a failed immutable-plan persistence result
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function planPersistenceFailure(
        string $findingId,
        string $message,
        string $nextAction,
        string $planId,
        string $artifactPath,
        array $performedEffects = []
    ): MachineResult {
        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'plan',
            'capability'              => 'release_planning',
            'status'                  => 'policy_blocked',
            'exit_class'              => 'failed',
            'plan_id'                 => $planId,
            'artifact'                => ['plan_id' => $planId, 'path' => $artifactPath],
            'findings'                => [['id' => $findingId, 'message' => $message]],
            'verified_postconditions' => [],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => $nextAction]
        ], 4);
    }

    /**
     * Builds a classified deterministic plan-boundary outcome
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function planBoundaryOutcome(
        ReleaseBoundaryOutcome $outcome,
        string $planId,
        string $artifactPath,
        array $performedEffects = []
    ): MachineResult {
        $classification = $outcome->classification();

        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'plan',
            'capability'              => 'release_planning',
            'status'                  => $classification['status'],
            'exit_class'              => $classification['exit_class'],
            'plan_id'                 => $planId,
            'artifact'                => ['plan_id' => $planId, 'path' => $artifactPath],
            'findings'                => [[
                'id'      => 'release.boundary.'.$outcome->value,
                'outcome' => $outcome->value,
                'message' => 'The deterministic plan artifact boundary classified its configured outcome.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => $classification['next_action']]
        ], $classification['exit_code']);
    }

    /**
     * Builds a classified value-producing boundary outcome before an identity exists
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function planBoundaryValueOutcome(
        ReleaseBoundaryOutcome $outcome,
        array $performedEffects = []
    ): MachineResult {
        $classification = $outcome->classification();

        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'plan',
            'capability'              => 'release_planning',
            'status'                  => $classification['status'],
            'exit_class'              => $classification['exit_class'],
            'findings'                => [[
                'id'      => 'release.boundary.'.$outcome->value,
                'outcome' => $outcome->value,
                'message' => 'The deterministic plan value boundary classified its configured outcome.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => $classification['next_action']]
        ], $classification['exit_code']);
    }

    /**
     * Builds one canonical packaged release result
     *
     * @param string $planId Immutable plan identity.
     * @param string $runId Named run identity.
     * @param string $candidateOid Exact candidate commit OID.
     * @param string $archiveDigest SHA-256 digest of the deterministic archive.
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function packaged(
        string $planId,
        string $runId,
        string $candidateOid,
        string $archiveDigest,
        ReleasePackageEffectSet $effectSet,
        array $performedEffects
    ): MachineResult {
        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'package',
            'capability'              => 'release_packaging',
            'status'                  => 'packaged',
            'exit_class'              => 'success',
            'plan_id'                 => $planId,
            'run_id'                  => $runId,
            'candidate_oid'           => $candidateOid,
            'archive_digest'          => $archiveDigest,
            'effect_set'              => $effectSet->toArray(),
            'findings'                => [[
                'id'      => 'release.package.completed',
                'message' => 'The deterministic release archive was created and its identity was bound.'
            ]],
            'verified_postconditions' => [
                'phase_handoff_revalidated',
                'archive_created_and_verified'
            ],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'certify_release_package']
        ], 0);
    }

    /**
     * Builds one effect-set refusal stop for a package whose effects were not approved
     *
     * @param string $planId Immutable plan identity.
     * @param string $runId Named run identity.
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function packageRefusal(
        string $planId,
        string $runId,
        array $performedEffects
    ): MachineResult {
        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'package',
            'capability'              => 'release_packaging',
            'status'                  => 'authority_required',
            'exit_class'              => 'refused',
            'plan_id'                 => $planId,
            'run_id'                  => $runId,
            'findings'                => [[
                'id'      => 'release.package.effect_set_refused',
                'message' => 'The packaging effect set was not approved for the exact bounded local effects.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'approve_exact_packaging_effects']
        ], 3);
    }

    /**
     * Builds one already-satisfied result when a matching archived already exists
     *
     * @param string $planId Immutable plan identity.
     * @param string $runId Named run identity.
     * @param string $candidateOid Exact candidate commit OID.
     * @param string $archiveDigest SHA-256 digest of the existing archive.
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function packageAlreadySatisfied(
        string $planId,
        string $runId,
        string $candidateOid,
        string $archiveDigest,
        ReleasePackageEffectSet $effectSet,
        array $performedEffects
    ): MachineResult {
        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'package',
            'capability'              => 'release_packaging',
            'status'                  => 'packaged',
            'exit_class'              => 'success',
            'plan_id'                 => $planId,
            'run_id'                  => $runId,
            'candidate_oid'           => $candidateOid,
            'archive_digest'          => $archiveDigest,
            'effect_set'              => $effectSet->toArray(),
            'findings'                => [[
                'id'      => 'release.package.already_satisfied',
                'message' => 'The deterministic release archive already existed and was verified.'
            ]],
            'verified_postconditions' => [
                'phase_handoff_revalidated',
                'archive_already_persisted'
            ],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'certify_release_package']
        ], 0);
    }

    /**
     * Builds one classified archive-creation stop
     *
     * @param string $stop Classified archive stop.
     * @param string $planId Immutable plan identity.
     * @param string $runId Named run identity.
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $performedEffects
     */
    public function packageArchiveStop(
        string $stop,
        string $planId,
        string $runId,
        array $performedEffects
    ): MachineResult {
        [$status, $exitClass, $exitCode, $finding, $message, $action] = match ($stop) {
            'archive_refused' => [
                'authority_required',
                'refused',
                3,
                'release.package.archive_creation_refused',
                'The deterministic archive creation was refused by the archive provider.',
                'obtain_archive_creation_authority'
            ],
            'archive_failed' => [
                'policy_blocked',
                'failed',
                4,
                'release.package.archive_creation_failed',
                'The deterministic archive could not be created.',
                'repair_archive_creation_provider'
            ],
            'archive_uncertain' => [
                'evidence_indeterminate',
                'uncertain',
                5,
                'release.package.archive_creation_uncertain',
                'The archive creation outcome could not be determined.',
                'reconcile_archive_creation'
            ],
            'archive_drift' => [
                'stale_plan',
                'drifted',
                6,
                'release.package.archive_creation_drift',
                'The candidate commit identity drifted during archive creation.',
                'create_current_release_plan'
            ],
            default => [
                'evidence_indeterminate',
                'uncertain',
                5,
                'release.package.archive_creation_indeterminate',
                'The archive creation state is indeterminate.',
                'reconcile_archive_creation'
            ]
        };

        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'package',
            'capability'              => 'release_packaging',
            'status'                  => $status,
            'exit_class'              => $exitClass,
            'plan_id'                 => $planId,
            'run_id'                  => $runId,
            'findings'                => [['id' => $finding, 'message' => $message]],
            'verified_postconditions' => [],
            'performed_effects'       => $performedEffects,
            'proposed_effects'        => [],
            'next_action'             => ['action' => $action]
        ], $exitCode);
    }
}
