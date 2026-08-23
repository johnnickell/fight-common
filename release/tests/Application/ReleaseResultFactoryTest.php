<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Adapter\Fake\DeterministicReleaseBoundaryFake;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\MachineResult;
use Fight\Release\Application\ReleaseCommand;
use Fight\Release\Application\ReleasePlanValidationFailure;
use Fight\Release\Application\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
#[CoversClass(ReleaseResultFactory::class)]
#[CoversClass(ReleaseCommand::class)]
/**
 * Class ReleaseResultFactoryTest
 *
 * Covers the stable release failure result contract.
 */
class ReleaseResultFactoryTest extends UnitTestCase
{
    /**
     * Covers the canonical infrastructure result for each public command family.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_runtime_failure_normalizes_command_and_owns_the_exit_seventy_payload(): void
    {
        $factory = new ReleaseResultFactory();

        foreach (
            [
            'inspect'       => ['inspect', 'release_inspection'],
            'plan'          => ['plan', 'release_planning'],
            'prepare'       => ['prepare', 'release_preparation'],
            'compatibility' => ['compatibility', 'compatibility_assessment'],
            'publish'       => ['unknown', 'unsupported_command']
            ] as $requested => [$command, $capability]
        ) {
            $result = $factory->runtimeFailure($requested);

            self::assertSame(70, $result->exitCode);
            self::assertSame($command, $result->payload['command']);
            self::assertSame($capability, $result->payload['capability']);
            self::assertSame('infrastructure_unavailable', $result->payload['status']);
            self::assertSame('failed', $result->payload['exit_class']);
            self::assertSame(
                'release.runtime.bootstrap_unavailable',
                $result->payload['findings'][0]['id']
            );
            self::assertSame([], $result->payload['verified_postconditions']);
            self::assertSame([], $result->payload['performed_effects']);
            self::assertSame([], $result->payload['proposed_effects']);
            self::assertSame(
                ['action' => 'restore_release_runtime_and_retry'],
                $result->payload['next_action']
            );
        }
    }

    /**
     * Covers the governed result used after the canonical runtime starts but cannot return a result.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_runtime_termination_normalizes_command_and_owns_the_exit_seventy_one_payload(): void
    {
        $factory = new ReleaseResultFactory();

        foreach (
            [
            'inspect'       => ['inspect', 'release_inspection'],
            'plan'          => ['plan', 'release_planning'],
            'prepare'       => ['prepare', 'release_preparation'],
            'compatibility' => ['compatibility', 'compatibility_assessment'],
            'publish'       => ['unknown', 'unsupported_command']
            ] as $requested => [$command, $capability]
        ) {
            $result = $factory->runtimeTermination($requested);

            self::assertSame(71, $result->exitCode);
            self::assertSame($command, $result->payload['command']);
            self::assertSame($capability, $result->payload['capability']);
            self::assertSame('infrastructure_terminated', $result->payload['status']);
            self::assertSame('failed', $result->payload['exit_class']);
            self::assertSame('release.runtime.result_unavailable', $result->payload['findings'][0]['id']);
            self::assertSame([], $result->payload['verified_postconditions']);
            self::assertSame([], $result->payload['performed_effects']);
            self::assertSame([], $result->payload['proposed_effects']);
            self::assertSame(
                ['action' => 'inspect_release_runtime_termination'],
                $result->payload['next_action']
            );
        }
    }

    /**
     * Covers typed plan validation retaining its detail inside the coarse invalid-input class.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_validation_failure_preserves_its_stable_reason(): void
    {
        $result = new ReleaseResultFactory()->planValidationFailure(
            ReleasePlanValidationFailure::SOURCE_COMMIT_OID_MISSING,
            [['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_writable', 'outcome' => 'success']]
        );

        self::assertSame(2, $result->exitCode);
        self::assertSame('policy_blocked', $result->payload['status']);
        self::assertSame('invalid_input', $result->payload['exit_class']);
        self::assertSame('release.plan.source_commit_oid_missing', $result->payload['findings'][0]['id']);
        self::assertSame(
            'The release plan source commit oid is missing.',
            $result->payload['findings'][0]['message']
        );
        self::assertSame(['action' => 'provide_source_commit_oid'], $result->payload['next_action']);
        self::assertSame('filesystem.inspect_writable', $result->payload['performed_effects'][0]['effect_class']);
    }

    /**
     * Covers command-specific and unsupported-command invalid-input capabilities.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_failure_identifies_the_requested_command_capability(): void
    {
        $factory = new ReleaseResultFactory();

        self::assertSame(
            'release_inspection',
            $factory->failure(
                'inspect',
                'inspect.invalid',
                'Invalid inspection.',
                'repair_inspection'
            )->payload['capability']
        );
        self::assertSame(
            'release_planning',
            $factory->failure('plan', 'plan.invalid', 'Invalid plan.', 'repair_plan')->payload['capability']
        );
        self::assertSame(
            'release_preparation',
            $factory->failure(
                'prepare',
                'release.prepare.plan_forbidden',
                'Preparation requires one immutable plan below the repository .runs directory.',
                'select_immutable_release_plan',
                [[
                    'capability'   => 'filesystem',
                    'effect_class' => 'filesystem.inspect_runs_directory',
                    'outcome'      => 'refusal'
                ]]
            )->payload['capability']
        );
        self::assertSame(
            'compatibility_assessment',
            $factory->failure(
                'compatibility',
                'release.compatibility.arguments_invalid',
                'Compatibility accepts no caller-supplied policy, fixture, or success evidence.',
                'run_repository_compatibility_authority'
            )->payload['capability']
        );
        $unsupported = $factory->failure('publish', 'command.unsupported', 'Unsupported command.', 'select_command');

        self::assertSame(2, $unsupported->exitCode);
        self::assertSame('unsupported_command', $unsupported->payload['capability']);
        self::assertSame('invalid_input', $unsupported->payload['exit_class']);
        self::assertSame('command.unsupported', $unsupported->payload['findings'][0]['id']);
        self::assertSame(['action' => 'select_command'], $unsupported->payload['next_action']);
    }

    /**
     * Covers Application-owned ledger attachment for plan validation failures.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_normal_failure_reads_the_injected_effect_ledger_for_each_supported_command(): void
    {
        $effects = new DeterministicReleaseBoundaryFake();
        $effects->read('/missing-release-fixture');

        $result = new ReleaseResultFactory($effects)->failure(
            'plan',
            'release.plan.fixture_unreadable',
            'The fixture is unreadable.',
            'provide_readable_plan_fixture'
        );

        self::assertSame([
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'failure']
        ], $result->payload['performed_effects']);

        $inspection = new ReleaseResultFactory($effects)->failure(
            'inspect',
            'release.inspect.fixture_unreadable',
            'The fixture is unreadable.',
            'provide_readable_inspection_fixture'
        );

        self::assertSame([
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'failure']
        ], $inspection->payload['performed_effects']);

        $explicit = new ReleaseResultFactory()->failure(
            'plan',
            'release.plan.inputs_required',
            'The inputs are missing.',
            'provide_plan_inputs',
            []
        );

        self::assertSame([], $explicit->payload['performed_effects']);
    }

    /**
     * Covers an immutable-plan persistence failure with artifact identity evidence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_persistence_failure_reports_the_artifact_and_recovery_action(): void
    {
        $planId = str_repeat('a', 64);
        $result = new ReleaseResultFactory()->planPersistenceFailure(
            'release.plan.artifact_conflict',
            'The immutable artifact conflicts.',
            'resolve_plan_artifact_conflict',
            $planId,
            '/repo/.runs/'.$planId.'.json'
        );

        self::assertSame(4, $result->exitCode);
        self::assertSame('release_planning', $result->payload['capability']);
        self::assertSame('failed', $result->payload['exit_class']);
        self::assertSame($planId, $result->payload['plan_id']);
        self::assertSame([
            'plan_id' => $planId,
            'path'    => '/repo/.runs/'.$planId.'.json'
        ], $result->payload['artifact']);
        self::assertSame('release.plan.artifact_conflict', $result->payload['findings'][0]['id']);
        self::assertSame(['action' => 'resolve_plan_artifact_conflict'], $result->payload['next_action']);
    }

    /**
     * Covers a deterministic plan-boundary outcome without claiming persistence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_boundary_outcome_preserves_its_declared_machine_classification(): void
    {
        $planId = str_repeat('a', 64);
        $result = new ReleaseResultFactory()->planBoundaryOutcome(
            ReleaseBoundaryOutcome::UNCERTAINTY,
            $planId,
            '/repo/.runs/'.$planId.'.json'
        );

        self::assertSame(5, $result->exitCode);
        self::assertSame('evidence_indeterminate', $result->payload['status']);
        self::assertSame('uncertain', $result->payload['exit_class']);
        self::assertSame('uncertainty', $result->payload['findings'][0]['outcome']);
        self::assertSame([], $result->payload['verified_postconditions']);
        self::assertSame(['action' => 'reconcile_boundary_effect'], $result->payload['next_action']);
    }

    /**
     * Covers a value-producing boundary outcome before a plan identity exists.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_value_outcome_does_not_claim_an_artifact_identity(): void
    {
        $result = new ReleaseResultFactory()->planBoundaryValueOutcome(
            ReleaseBoundaryOutcome::FAILURE,
            [['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'failure']]
        );

        self::assertSame(4, $result->exitCode);
        self::assertSame('release.boundary.failure', $result->payload['findings'][0]['id']);
        self::assertArrayNotHasKey('plan_id', $result->payload);
        self::assertArrayNotHasKey('artifact', $result->payload);
        self::assertSame([
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'failure']
        ], $result->payload['performed_effects']);
    }

    /**
     * Covers successful preparation, idempotent resume, and every governed resume stop.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_preparation_results_preserve_exact_state_artifact_and_stop_contracts(): void
    {
        $factory = new ReleaseResultFactory();
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $state = [
            'history_path'    => '/repo/.runs/runs/'.$runId.'/history.jsonl',
            'projection_path' => '/repo/.runs/runs/'.$runId.'/projection.json'
        ];
        $artifacts = [
            'evidence_manifest' => [
                'manifest_id' => str_repeat('c', 64),
                'path'        => '/repo/.runs/'.str_repeat('c', 64).'.evidence-manifest.json'
            ],
            'phase_handoff'     => [
                'handoff_id' => str_repeat('d', 64),
                'path'       => '/repo/.runs/'.str_repeat('d', 64).'.phase-handoff.json'
            ]
        ];
        $revalidated = [
            [
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.inspect_runs_directory',
                'outcome'      => 'success'
            ],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success']
        ];
        $artifactEffects = [
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success']
        ];
        $liveAuthority = [
            ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
            ['capability' => 'authorization', 'effect_class' => 'authorization.check', 'outcome' => 'success']
        ];

        $prepared = $factory->prepared(
            $planId,
            $runId,
            $state,
            $artifacts,
            [...$revalidated, ...$artifactEffects, ...$liveAuthority]
        );
        self::assertSame('release.prepare.completed', $prepared->payload['findings'][0]['id']);
        self::assertSame($state, $prepared->payload['run_state']);

        $resumed = $factory->resumedPrepared($planId, $runId, $state, $artifacts, [
            ...$revalidated,
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
            ...$liveAuthority
        ]);
        self::assertSame('release.prepare.already_satisfied', $resumed->payload['findings'][0]['id']);
        self::assertSame('prepared_postconditions_reverified', $resumed->payload['verified_postconditions'][3]);

        $resumedCompleted = $factory->resumedPreparationCompleted(
            $planId,
            $runId,
            $state,
            $artifacts,
            [
                ...$revalidated,
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ...$artifactEffects,
                ...$liveAuthority
            ]
        );
        self::assertSame('release.prepare.resumed_completed', $resumedCompleted->payload['findings'][0]['id']);
        self::assertSame(
            'prepared_postconditions_verified',
            $resumedCompleted->payload['verified_postconditions'][3]
        );

        $expected = [
            'missing'                       => [5, 'evidence_indeterminate', 'release.prepare.resume_state_missing'],
            'conflict'                      => [23, 'conflict', 'release.prepare.resume_contention'],
            'stale'                         => [6, 'stale_plan', 'release.prepare.resume_plan_drift'],
            'indeterminate'                 => [
                5, 'evidence_indeterminate', 'release.prepare.resume_state_indeterminate'
            ],
            'failed'                        => [4, 'policy_blocked', 'release.prepare.state_persistence_failed'],
            'create_conflict'               => [23, 'conflict', 'release.prepare.run_identity_conflict'],
            'state_indeterminate'           => [
                5, 'evidence_indeterminate', 'release.prepare.state_persistence_indeterminate'
            ],
            'artifact_indeterminate'        => [5, 'evidence_indeterminate', 'release.prepare.artifacts_indeterminate'],
            'baseline_missing'              => [4, 'policy_blocked', 'release.prepare.baseline_tag_missing'],
            'baseline_ambiguous'            => [4, 'policy_blocked', 'release.prepare.baseline_tag_ambiguous'],
            'baseline_duplicate_normalized' => [
                4, 'policy_blocked', 'release.prepare.baseline_tag_duplicate_normalized'
            ],
            'baseline_non_ancestor'         => [4, 'policy_blocked', 'release.prepare.baseline_tag_non_ancestor'],
            'baseline_drift'                => [6, 'stale_plan', 'release.prepare.baseline_resolution_drift']
        ];

        foreach ($expected as $stop => [$exitCode, $status, $finding]) {
            $causal = match ($stop) {
                'missing', 'stale', 'indeterminate' => [
                    'capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'uncertainty'
                ],
                'conflict' => [
                    'capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'refusal'
                ],
                'failed', 'artifact_indeterminate' => [
                    'capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure'
                ],
                'create_conflict' => [
                    'capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'refusal'
                ],
                'state_indeterminate' => [
                    'capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'uncertainty'
                ],
                'baseline_drift' => [
                    'capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'drift'
                ],
                'baseline_missing', 'baseline_ambiguous', 'baseline_duplicate_normalized',
                'baseline_non_ancestor' => [
                    'capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'
                ]
            };

            $effects = [...$revalidated, $causal];

            $effects = [...$effects, ...$artifactEffects];

            $result = $factory->prepareResumeStop($stop, $planId, $runId, $artifacts, $effects);
            self::assertSame($exitCode, $result->exitCode);
            self::assertSame($status, $result->payload['status']);
            self::assertSame($finding, $result->payload['findings'][0]['id']);
            self::assertSame($artifacts, $result->payload['artifacts']);
        }

        $persistenceFailure = $factory->prepareEvidencePersistenceFailure(
            $planId,
            $runId,
            [
                ...$revalidated,
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure']
            ]
        );
        self::assertSame(5, $persistenceFailure->exitCode);
        self::assertSame('prepare', $persistenceFailure->payload['command']);
        self::assertSame('evidence_indeterminate', $persistenceFailure->payload['status']);
        self::assertSame(
            'release.prepare.evidence_persistence_failed',
            $persistenceFailure->payload['findings'][0]['id']
        );
        self::assertSame(['action' => 'repair_release_evidence_storage'], $persistenceFailure->payload['next_action']);
        self::assertArrayNotHasKey('artifacts', $persistenceFailure->payload);
        self::assertSame(
            [
                ...$revalidated,
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure']
            ],
            $persistenceFailure->payload['performed_effects']
        );
        $refusedPersistence = $factory->prepareEvidencePersistenceFailure(
            $planId,
            $runId,
            [
                ...$revalidated,
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'refusal']
            ]
        );
        self::assertSame(5, $refusedPersistence->exitCode);
        self::assertArrayNotHasKey('artifacts', $refusedPersistence->payload);

        $resumedPersistenceFailure = $factory->resumedPrepareEvidencePersistenceFailure(
            $planId,
            $runId,
            $state,
            $revalidated
        );
        self::assertSame(5, $resumedPersistenceFailure->exitCode);
        self::assertSame($state, $resumedPersistenceFailure->payload['run_state']);
        self::assertSame([
            'run_event_chain_revalidated',
            'stopped_run_projection_revalidated'
        ], $resumedPersistenceFailure->payload['verified_postconditions']);
        self::assertSame(
            ['action' => 'repair_release_evidence_storage'],
            $resumedPersistenceFailure->payload['next_action']
        );

        foreach (
            [
                'finding'   => ['findings' => [['id' => 'release.prepare.other', 'message' => 'Other.']]],
                'action'    => ['next_action' => ['action' => 'reconcile_named_release_run']],
                'artifacts' => ['artifacts' => $artifacts]
            ] as $replacementName => $replacement
        ) {
            self::assertFalse(MachineResult::isValidPayload(
                array_replace($persistenceFailure->payload, $replacement),
                5
            ), $replacementName);
        }

        $this->expectException(InvalidArgumentException::class);
        $factory->prepareResumeStop('indeterminate', $planId, $runId, null, [[
            'capability'   => 'filesystem',
            'effect_class' => 'filesystem.read',
            'outcome'      => 'success'
        ]]);
    }
}

// phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
