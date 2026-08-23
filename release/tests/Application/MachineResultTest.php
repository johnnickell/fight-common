<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffect;
use Fight\Release\Application\CompatibilityAssessment;
use Fight\Release\Application\MachineResult;
use Fight\Release\Application\ReleaseCommand;
use Fight\Release\Application\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
#[CoversClass(MachineResult::class)]
#[CoversClass(ReleaseCommand::class)]
#[CoversClass(ReleaseEffect::class)]
#[CoversClass(CompatibilityAssessment::class)]
/**
 * Class MachineResultTest
 *
 * Covers fail-closed validation of the stable machine-result contract.
 */
final class MachineResultTest extends UnitTestCase
{
    /**
     * Covers construction of one complete v1 result.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_construction_binds_the_process_exit_code_into_a_complete_payload(): void
    {
        $result = new MachineResult($this->payload(), 4);

        self::assertSame(4, $result->exitCode);
        self::assertSame(4, $result->payload['exit_code']);
        self::assertTrue(MachineResult::isValidPayload($result->payload, 4));
    }

    /**
     * Covers the shared effect vocabulary used by results, plans, and deterministic boundaries.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_release_effect_vocabulary_owns_canonical_capabilities_and_postcondition_evidence(): void
    {
        $expectedCapabilities = [
            'filesystem.read'                   => 'filesystem',
            'filesystem.write'                  => 'filesystem',
            'filesystem.inspect_directory'      => 'filesystem',
            'filesystem.inspect_writable'       => 'filesystem',
            'filesystem.inspect_exists'         => 'filesystem',
            'filesystem.inspect_runs_directory' => 'filesystem',
            'archive.create'                    => 'archive',
            'archive.verify'                    => 'archive',
            'git.inspect_repository'            => 'git',
            'git.resolve_ref'                   => 'git',
            'hashing.sha256'                    => 'hashing',
            'clock.now'                         => 'clock',
            'signing.verify'                    => 'signing',
            'authorization.check'               => 'authorization',
            'github.release'                    => 'github',
            'packagist.publish'                 => 'packagist'
        ];

        foreach ($expectedCapabilities as $effectClass => $capability) {
            self::assertSame($capability, ReleaseEffect::from($effectClass)->capability());
        }

        self::assertSame([
            'archive.create',
            'archive.verify',
            'authorization.check',
            'clock.now',
            'filesystem.inspect_directory',
            'filesystem.inspect_exists',
            'filesystem.inspect_runs_directory',
            'filesystem.inspect_writable',
            'filesystem.read',
            'filesystem.write',
            'git.inspect_repository',
            'git.resolve_ref',
            'github.release',
            'hashing.sha256',
            'packagist.publish',
            'signing.verify'
        ], ReleaseEffect::canonicalValues());
        self::assertSame(
            'github_release_exists',
            ReleaseEffect::GITHUB_RELEASE->configuredAlreadySatisfiedEvidence()
        );
        self::assertSame(
            'packagist_version_exists',
            ReleaseEffect::PACKAGIST_PUBLISH->configuredAlreadySatisfiedEvidence()
        );
        self::assertNull(ReleaseEffect::FILESYSTEM_WRITE->configuredAlreadySatisfiedEvidence());
    }

    /**
     * Covers the stable status, exit-class, and process-code convention.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_each_governed_process_classification_is_accepted(): void
    {
        foreach (
            [
            0 => ['succeeded', 'success'],
            2 => ['policy_blocked', 'invalid_input'],
            3 => ['authority_required', 'refused'],
            4 => ['policy_blocked', 'failed'],
            5 => ['evidence_indeterminate', 'uncertain'],
            6 => ['stale_plan', 'drifted']
            ] as $exitCode => [$status, $exitClass]
        ) {
            $payload = $exitCode === 0 ? $this->inspectionPayload() : $this->payload();
            $payload['status'] = $status;
            $payload['exit_class'] = $exitClass;
            $payload['exit_code'] = $exitCode;

            self::assertTrue(MachineResult::isValidPayload($payload, $exitCode));
        }

        $unsupported = $this->payload();
        $unsupported['command'] = 'publish';
        $unsupported['capability'] = 'unsupported_command';
        self::assertTrue(MachineResult::isValidPayload($unsupported, 4));
    }

    /**
     * Covers the sole governed infrastructure exit and its command-specific capability.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_runtime_bootstrap_failure_is_the_only_valid_exit_seventy_shape(): void
    {
        foreach (
            [
            'inspect'       => 'release_inspection',
            'plan'          => 'release_planning',
            'prepare'       => 'release_preparation',
            'compatibility' => 'compatibility_assessment',
            'unknown'       => 'unsupported_command'
            ] as $command => $capability
        ) {
            self::assertTrue(MachineResult::isValidPayload(
                $this->runtimeBootstrapPayload($command, $capability),
                70
            ));
        }

        $payload = $this->runtimeBootstrapPayload();
        $invalid = [
            ['command' => 'publish'],
            ['capability' => 'unsupported_command'],
            ['status' => 'policy_blocked'],
            ['exit_class' => 'invalid_input'],
            ['exit_code' => 4],
            ['findings' => [['id' => 'release.inspect.failure', 'message' => 'Inspection failed.']]],
            ['findings' => [...$payload['findings'], ...$payload['findings']]],
            ['verified_postconditions' => ['minimum_increment_recommendation_derived']],
            ['performed_effects' => [[
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.read',
                'outcome'      => 'failure'
            ]]],
            ['proposed_effects' => [['effect_class' => 'git.tag']]],
            ['next_action' => ['action' => 'repair_test_failure']],
            ['resolved_inputs' => []],
            ['recommendation' => []],
            ['plan_id' => str_repeat('a', 64)],
            ['artifact' => ['plan_id' => str_repeat('a', 64), 'path' => '/artifact.json']]
        ];

        foreach ($invalid as $replacement) {
            self::assertFalse(MachineResult::isValidPayload(array_replace($payload, $replacement), 70));
        }
    }

    /**
     * Covers rejection of incomplete, inconsistent, and structurally malformed common fields.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_incomplete_or_malformed_common_fields_are_rejected(): void
    {
        $payload = $this->payload();
        $invalid = [];

        foreach (
            [
            'schema_version', 'command', 'capability', 'status', 'exit_class', 'exit_code', 'findings',
            'verified_postconditions', 'performed_effects', 'proposed_effects', 'next_action'
            ] as $field
        ) {
            $candidate = $payload;
            unset($candidate[$field]);
            $invalid[] = $candidate;
        }

        foreach (
            [
            ['schema_version' => 'v0'],
            ['command' => ''],
            ['command' => []],
            ['capability' => 'release_planning'],
            ['exit_code' => '4'],
            ['status' => 'succeeded'],
            ['unexpected' => true],
            ['findings' => []],
            ['findings' => 'finding'],
            ['findings' => ['not-an-object']],
            ['findings' => [['id' => '', 'message' => 'message']]],
            ['findings' => [['id' => 'id', 'message' => '']]],
            ['findings' => [['id' => 'id', 'message' => 'message', 'extra' => true]]],
            ['findings' => [['id' => 'id', 'message' => 'message', 'outcome' => []]]],
            ['verified_postconditions' => ['key' => 'value']],
            ['verified_postconditions' => ['']],
            ['performed_effects' => ['not-an-effect']],
            ['performed_effects' => [['capability' => 'git', 'effect_class' => 'git.inspect_repository']]],
            ['performed_effects' => [[
                'capability' => '', 'effect_class' => 'git.inspect_repository', 'outcome' => 'success'
            ]]],
            ['proposed_effects' => ['not-an-effect']],
            ['proposed_effects' => [['effect_class' => 'git.tag', 'extra' => true]]],
            ['proposed_effects' => [['effect_class' => '']]],
            ['next_action' => []],
            ['next_action' => ['action' => '']],
            ['next_action' => ['action' => 'repair', 'extra' => true]],
            ['next_action' => ['action' => 'repair', 'version' => []]]
            ] as $replacement
        ) {
            $invalid[] = array_replace($payload, $replacement);
        }

        foreach ($invalid as $candidate) {
            self::assertFalse(MachineResult::isValidPayload($candidate, 4));
        }

        self::assertFalse(MachineResult::isValidPayload($payload, 70));
        self::assertFalse(MachineResult::isValidPayload($payload, 71));
        self::assertFalse(MachineResult::isValidPayload($payload, 72));
    }

    /**
     * Covers the closed finding-outcome vocabulary and its result classification binding.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_finding_outcomes_are_governed_and_match_the_completed_result_classification(): void
    {
        foreach (ReleaseBoundaryOutcome::cases() as $outcome) {
            $classification = $outcome->classification();
            $payload = $this->payload();

            if ($outcome === ReleaseBoundaryOutcome::SUCCESS) {
                $payload = $this->inspectionPayload();
            }

            $payload['status'] = $classification['status'];
            $payload['exit_class'] = $classification['exit_class'];
            $payload['exit_code'] = $classification['exit_code'];
            $payload['findings'][0]['outcome'] = $outcome->value;

            self::assertTrue(
                MachineResult::isValidPayload($payload, $classification['exit_code']),
                $outcome->value
            );
            self::assertSame(
                $outcome->value,
                new MachineResult($payload, $classification['exit_code'])->payload['findings'][0]['outcome']
            );
        }

        $payload = $this->payload();

        foreach (['unknown', 'crash', "\xC3\x28"] as $outcome) {
            $payload['findings'][0]['outcome'] = $outcome;
            self::assertFalse(MachineResult::isValidPayload($payload, 4), $outcome);
        }

        $mismatched = $this->payload();
        $mismatched['findings'][0]['outcome'] = ReleaseBoundaryOutcome::DRIFT->value;
        self::assertFalse(MachineResult::isValidPayload($mismatched, 4));

        $extra = $this->payload();
        $extra['findings'][0] = [
            ...$extra['findings'][0],
            'outcome' => ReleaseBoundaryOutcome::FAILURE->value,
            'extra'   => true
        ];
        self::assertFalse(MachineResult::isValidPayload($extra, 4));
    }

    /**
     * Covers the two exact infrastructure result shapes and their distinct lifecycle boundaries.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_runtime_bootstrap_and_post_start_termination_have_distinct_exact_payloads(): void
    {
        $factory = new ReleaseResultFactory();
        $bootstrap = $factory->runtimeFailure('inspect')->payload;
        $termination = $factory->runtimeTermination('inspect')->payload;

        self::assertTrue(MachineResult::isValidPayload($bootstrap, 70));
        self::assertTrue(MachineResult::isValidPayload($termination, 71));
        self::assertFalse(MachineResult::isValidPayload($bootstrap, 71));
        self::assertFalse(MachineResult::isValidPayload($termination, 70));

        foreach (
            [
            ['status' => 'infrastructure_unavailable'],
            ['exit_class' => 'infrastructure_terminated'],
            ['findings' => [['id' => 'release.runtime.terminated', 'message' => 'Wrong finding.']]],
            ['verified_postconditions' => ['runtime_started']],
            ['performed_effects' => [[
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.read',
                'outcome'      => 'failure'
            ]]],
            ['proposed_effects' => [['effect_class' => 'filesystem.read']]],
            ['next_action' => ['action' => 'restore_release_runtime_and_retry']],
            ['extra' => true]
            ] as $replacement
        ) {
            self::assertFalse(MachineResult::isValidPayload(array_replace($termination, $replacement), 71));
        }
    }

    /**
     * Covers the closed performed-effect ledger vocabulary and exact entry schema.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_performed_effects_require_valid_capability_effect_and_outcome_combinations(): void
    {
        $payload = $this->payload();

        foreach (
            [
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'already_satisfied'],
            ['capability' => 'github', 'effect_class' => 'github.release', 'outcome' => 'already_satisfied'],
            ['capability' => 'packagist', 'effect_class' => 'packagist.publish', 'outcome' => 'already_satisfied'],
            ['capability' => 'git', 'effect_class' => 'git.inspect_repository', 'outcome' => 'success'],
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'failure']
            ] as $effect
        ) {
            self::assertTrue(MachineResult::isValidPayload([
                ...$payload,
                'performed_effects' => [$effect]
            ], 4));
        }

        foreach (
            [
            ['capability' => 'filesystem', 'effect_class' => 'git.inspect_repository', 'outcome' => 'success'],
            ['capability' => 'git', 'effect_class' => 'git.unknown', 'outcome' => 'success'],
            ['capability' => 'git', 'effect_class' => 'git.inspect_repository', 'outcome' => 'unknown'],
            ['capability' => 'git', 'effect_class' => 'git.inspect_repository', 'outcome' => 'crash'],
            ['capability' => 'git', 'effect_class' => 'git.inspect_repository', 'outcome' => 'already_satisfied'],
            [
                'capability'   => 'git',
                'effect_class' => 'git.inspect_repository',
                'outcome'      => 'success',
                'extra'        => true
            ]
            ] as $effect
        ) {
            self::assertFalse(MachineResult::isValidPayload([
                ...$payload,
                'performed_effects' => [$effect]
            ], 4));
        }
    }

    /**
     * Covers the closed, canonical proposed-effect set.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_proposed_effects_require_known_unique_effect_classes_in_canonical_order(): void
    {
        $payload = $this->payload();
        $valid = [
            ['effect_class' => 'filesystem.read'],
            ['effect_class' => 'git.inspect_repository'],
            ['effect_class' => 'hashing.sha256']
        ];

        self::assertTrue(MachineResult::isValidPayload([...$payload, 'proposed_effects' => $valid], 4));

        foreach (
            [
            [['effect_class' => 'git.unknown']],
            [['effect_class' => 'git']],
            [['effect_class' => 'git.inspect_repository'], ['effect_class' => 'git.inspect_repository']],
            [['effect_class' => 'hashing.sha256'], ['effect_class' => 'filesystem.read']]
            ] as $effects
        ) {
            self::assertFalse(MachineResult::isValidPayload([
                ...$payload,
                'proposed_effects' => $effects
            ], 4));
        }
    }

    /**
     * Covers recursive UTF-8 validity for payload keys and string values.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_every_nested_payload_key_and_string_value_must_be_valid_utf8(): void
    {
        $invalidUtf8 = "\xC3\x28";
        $payload = $this->payload();
        $invalidKey = $payload;
        $invalidKey['findings'][0][$invalidUtf8] = 'value';
        $invalidValue = $payload;
        $invalidValue['findings'][0]['message'] = $invalidUtf8;

        foreach ([$invalidKey, $invalidValue] as $candidate) {
            self::assertFalse(MachineResult::isValidPayload($candidate, 4));

            try {
                new MachineResult($candidate, 4);
                self::fail('Invalid UTF-8 must never cross the machine-result constructor.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(
                    'The release machine result does not satisfy the v1 contract.',
                    $exception->getMessage()
                );
            }
        }
    }

    /**
     * Covers command-specific inspection fields.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_successful_inspection_requires_complete_valid_evidence(): void
    {
        $payload = $this->inspectionPayload();
        self::assertTrue(MachineResult::isValidPayload($payload, 0));

        $historical = $payload;
        $historical['resolved_inputs']['baseline_tag'] = '1.1.0';
        $historical['recommendation']['recommended_version'] = '1.1.1';
        $historical['next_action']['version'] = '1.1.1';
        self::assertTrue(MachineResult::isValidPayload($historical, 0));

        foreach (
            [
            ['resolved_inputs' => 'invalid'],
            ['resolved_inputs' => ['source_commit' => 'only-one-field']],
            ['resolved_inputs' => [
                'source_commit'       => '',
                'baseline_tag'        => 'v1.0.0',
                'baseline_tag_object' => str_repeat('a', 40),
                'baseline_commit'     => str_repeat('b', 40),
                'support_policy'      => 'supported'
            ]],
            ['resolved_inputs' => [
                'source_commit'       => str_repeat('C', 40),
                'baseline_tag'        => 'v1.0.0',
                'baseline_tag_object' => str_repeat('a', 40),
                'baseline_commit'     => str_repeat('b', 40),
                'support_policy'      => 'supported'
            ]],
            ['resolved_inputs' => [
                'source_commit'       => str_repeat('c', 40),
                'baseline_tag'        => 'release-1.0.0',
                'baseline_tag_object' => str_repeat('a', 40),
                'baseline_commit'     => str_repeat('b', 40),
                'support_policy'      => 'supported'
            ]],
            ['resolved_inputs' => [
                'source_commit'       => str_repeat('c', 40),
                'baseline_tag'        => 'v01.0.0',
                'baseline_tag_object' => str_repeat('a', 40),
                'baseline_commit'     => str_repeat('b', 40),
                'support_policy'      => 'supported'
            ]],
            ['recommendation' => 'invalid'],
            ['recommendation' => ['minimum_increment' => 'patch']],
            ['recommendation' => [
                'minimum_increment'   => 'build',
                'recommended_version' => '1.0.1',
                'authoritative'       => false
            ]],
            ['recommendation' => [
                'minimum_increment'   => 'patch',
                'recommended_version' => '01.0.1',
                'authoritative'       => false
            ]],
            ['recommendation' => [
                'minimum_increment'   => 'patch',
                'recommended_version' => '1.0.1',
                'authoritative'       => true
            ]],
            ['verified_postconditions' => []],
            ['verified_postconditions' => ['inspection_boundary_effect_completed']],
            ['next_action' => ['action' => 'approve_exact_version_for_plan', 'version' => '1.0.2']]
            ] as $replacement
        ) {
            self::assertFalse(MachineResult::isValidPayload(array_replace($payload, $replacement), 0));
        }

        foreach (['resolved_inputs', 'recommendation'] as $field) {
            $incomplete = $payload;
            unset($incomplete[$field]);
            self::assertFalse(MachineResult::isValidPayload($incomplete, 0), $field);
        }

        $malformedAssessment = $payload;
        $malformedAssessment['recommendation']['compatibility_assessment'] = 'invalid';
        self::assertFalse(MachineResult::isValidPayload($malformedAssessment, 0));

        $conflictingAggregate = $payload;
        $conflictingAggregate['recommendation']['minimum_increment'] = 'minor';
        $conflictingAggregate['recommendation']['recommended_version'] = '1.1.0';
        $conflictingAggregate['next_action']['version'] = '1.1.0';
        self::assertFalse(MachineResult::isValidPayload($conflictingAggregate, 0));

        $failure = $this->payload();
        self::assertTrue(MachineResult::isValidPayload($failure, 4));
        $failure['verified_postconditions'] = ['minimum_increment_recommendation_derived'];
        self::assertFalse(MachineResult::isValidPayload($failure, 4));
    }

    /**
     * Covers command-specific plan identity and artifact fields.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_successful_plan_requires_paired_identity_artifact_and_verified_postcondition(): void
    {
        $payload = $this->payload();
        $payload['command'] = 'plan';
        $payload['capability'] = 'release_planning';
        $payload['status'] = 'succeeded';
        $payload['exit_class'] = 'success';
        $payload['exit_code'] = 0;
        $planId = str_repeat('a', 64);
        $payload['plan_id'] = $planId;
        $payload['artifact'] = ['plan_id' => $planId, 'path' => '/repo/.runs/'.$planId.'.json'];
        $payload['findings'] = [[
            'id'      => 'release.plan.created',
            'message' => 'The immutable plan was created.'
        ]];
        $payload['verified_postconditions'] = ['immutable_release_plan_persisted'];
        $payload['next_action'] = ['action' => 'create_release_run'];

        self::assertTrue(MachineResult::isValidPayload($payload, 0));

        foreach (
            [
            ['plan_id' => 'not-sha256'],
            ['artifact' => 'invalid'],
            ['artifact' => ['plan_id' => $planId]],
            ['artifact' => ['plan_id' => '', 'path' => '/artifact.json']],
            ['artifact' => ['plan_id' => str_repeat('b', 64), 'path' => '/artifact.json']],
            ['findings' => [['id' => 'release.plan.unknown', 'message' => 'Unknown success claim.']]],
            ['verified_postconditions' => []],
            ['verified_postconditions' => ['immutable_release_plan_already_persisted']],
            ['verified_postconditions' => ['immutable_release_plan_persisted', 'extra']],
            ['next_action' => ['action' => 'repair_plan_artifact_persistence']]
            ] as $replacement
        ) {
            self::assertFalse(MachineResult::isValidPayload(array_replace($payload, $replacement), 0));
        }

        foreach (['plan_id', 'artifact'] as $field) {
            $incomplete = $payload;
            unset($incomplete[$field]);
            self::assertFalse(MachineResult::isValidPayload($incomplete, 0), $field);
        }

        $alreadySatisfied = $payload;
        $alreadySatisfied['findings'][0]['id'] = 'release.plan.already_satisfied';
        $alreadySatisfied['verified_postconditions'] = ['immutable_release_plan_already_persisted'];
        self::assertTrue(MachineResult::isValidPayload($alreadySatisfied, 0));

        $failure = $this->payload();
        $failure['command'] = 'plan';
        $failure['capability'] = 'release_planning';
        self::assertTrue(MachineResult::isValidPayload($failure, 4));
        $failure['verified_postconditions'] = ['immutable_release_plan_persisted'];
        self::assertFalse(MachineResult::isValidPayload($failure, 4));
    }

    /**
     * Covers content-addressed preparation artifact references.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_successful_preparation_requires_complete_content_addressed_artifacts(): void
    {
        $payload = $this->payload();
        $payload['command'] = 'prepare';
        $payload['capability'] = 'release_preparation';
        $payload['status'] = 'prepared';
        $payload['exit_class'] = 'success';
        $payload['exit_code'] = 0;
        $payload['plan_id'] = str_repeat('a', 64);
        $payload['run_id'] = str_repeat('b', 64);
        $payload['run_state'] = [
            'history_path'    => '/repo/.runs/runs/'.$payload['run_id'].'/history.jsonl',
            'projection_path' => '/repo/.runs/runs/'.$payload['run_id'].'/projection.json'
        ];
        $manifestId = str_repeat('c', 64);
        $handoffId = str_repeat('d', 64);
        $payload['artifacts'] = [
            'evidence_manifest' => [
                'manifest_id' => $manifestId,
                'path'        => '/repo/.runs/'.$manifestId.'.evidence-manifest.json'
            ],
            'phase_handoff'     => [
                'handoff_id' => $handoffId,
                'path'       => '/repo/.runs/'.$handoffId.'.phase-handoff.json'
            ]
        ];
        $payload['findings'] = [[
            'id'      => 'release.prepare.completed',
            'message' => 'The immutable plan was revalidated and a distinct prepared run was persisted.'
        ]];
        $payload['verified_postconditions'] = [
            'immutable_plan_revalidated',
            'prepared_run_projection_published'
        ];
        $payload['next_action'] = ['action' => 'package_release_run'];
        $payload['performed_effects'] = $this->successfulPreparationEffects(false);

        self::assertTrue(MachineResult::isValidPayload($payload, 0));

        foreach (
            [
            'invalid',
            [],
            ['evidence_manifest' => 'invalid', 'phase_handoff' => $payload['artifacts']['phase_handoff']],
            [
                'evidence_manifest' => ['manifest_id' => 'invalid', 'path' => '/invalid'],
                'phase_handoff'     => $payload['artifacts']['phase_handoff']
            ],
            [
                'evidence_manifest' => $payload['artifacts']['evidence_manifest'],
                'phase_handoff'     => ['handoff_id' => $handoffId, 'path' => '/wrong']
            ]
            ] as $artifacts
        ) {
            self::assertFalse(MachineResult::isValidPayload([...$payload, 'artifacts' => $artifacts], 0));
        }

        $missing = $payload;
        unset($missing['artifacts']);
        self::assertFalse(MachineResult::isValidPayload($missing, 0));

        $resumed = $payload;
        $resumed['findings'] = [[
            'id'      => 'release.prepare.already_satisfied',
            'message' => 'The named prepared run and every claimed postcondition were reverified.'
        ]];
        $resumed['verified_postconditions'] = [
            'immutable_plan_revalidated',
            'run_event_chain_revalidated',
            'prepared_run_projection_revalidated',
            'prepared_postconditions_reverified'
        ];
        $resumed['performed_effects'] = $this->successfulPreparationEffects(true);
        self::assertTrue(MachineResult::isValidPayload($resumed, 0));

        $resumedCompleted = $payload;
        $resumedCompleted['findings'] = [[
            'id'      => 'release.prepare.resumed_completed',
            'message' => 'The named release run was revalidated and preparation completed during resume.'
        ]];
        $resumedCompleted['verified_postconditions'] = [
            'immutable_plan_revalidated',
            'run_event_chain_revalidated',
            'prepared_run_projection_published',
            'prepared_postconditions_verified'
        ];
        $resumedCompleted['performed_effects'] = [
            ...$this->successfulPreparationEffects(true),
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success']
        ];
        self::assertTrue(MachineResult::isValidPayload($resumedCompleted, 0));

        foreach ([$payload, $resumed, $resumedCompleted] as $success) {
            self::assertFalse(MachineResult::isValidPayload([
                ...$success,
                'performed_effects' => []
            ], 0));
            self::assertFalse(MachineResult::isValidPayload([
                ...$success,
                'performed_effects' => [[
                    'capability' => 'clock', 'effect_class' => 'clock.now', 'outcome' => 'success'
                ]]
            ], 0));
            $wrongOutcome = $success['performed_effects'];
            $wrongOutcome[0]['outcome'] = 'failure';
            self::assertFalse(MachineResult::isValidPayload([
                ...$success,
                'performed_effects' => $wrongOutcome
            ], 0));
            self::assertFalse(MachineResult::isValidPayload([
                ...$success,
                'performed_effects' => [...$success['performed_effects'], [
                    'capability'   => 'authorization',
                    'effect_class' => 'authorization.check',
                    'outcome'      => 'failure'
                ]]
            ], 0), 'A later contradictory authority outcome must invalidate success.');
            self::assertFalse(MachineResult::isValidPayload([
                ...$success,
                'performed_effects' => [...$success['performed_effects'], [
                    'capability'   => 'clock',
                    'effect_class' => 'clock.now',
                    'outcome'      => 'success'
                ]]
            ], 0), 'An unrelated same-phase effect must invalidate success.');
        }

        $crossRoot = $payload;
        $crossRootHandoff = $crossRoot['artifacts']['phase_handoff'];
        $crossRoot['artifacts']['phase_handoff']['path'] = sprintf(
            '/other-root/%s.phase-handoff.json',
            $crossRootHandoff['handoff_id']
        );
        self::assertFalse(MachineResult::isValidPayload($crossRoot, 0));
        $crossRoot = $payload;
        $crossRoot['run_state']['projection_path'] = '/other-root/runs/'.$crossRoot['run_id'].'/projection.json';
        self::assertFalse(MachineResult::isValidPayload($crossRoot, 0));

        foreach (['/repo/./.runs', '/repo/cache/../.runs', '/repo//.runs'] as $nonCanonicalRoot) {
            $nonCanonical = $payload;
            $nonCanonical['run_state'] = [
                'history_path'    => $nonCanonicalRoot.'/runs/'.$payload['run_id'].'/history.jsonl',
                'projection_path' => $nonCanonicalRoot.'/runs/'.$payload['run_id'].'/projection.json'
            ];
            $nonCanonical['artifacts']['evidence_manifest']['path'] = sprintf(
                '%s/%s.evidence-manifest.json',
                $nonCanonicalRoot,
                $manifestId
            );
            $nonCanonical['artifacts']['phase_handoff']['path'] = sprintf(
                '%s/%s.phase-handoff.json',
                $nonCanonicalRoot,
                $handoffId
            );
            self::assertFalse(
                MachineResult::isValidPayload($nonCanonical, 0),
                'Preparation paths must reject non-canonical root '.$nonCanonicalRoot
            );
        }

        foreach (
            [
            ['plan_id' => 'invalid'],
            ['run_id' => 'invalid'],
            ['run_state' => 'invalid'],
            ['run_state' => ['history_path' => '/history-only']],
            ['run_state' => ['history_path' => '', 'projection_path' => '/projection']]
            ] as $replacement
        ) {
            self::assertFalse(MachineResult::isValidPayload(array_replace($payload, $replacement), 0));
        }

        $failure = $this->payload();
        $failure['command'] = 'prepare';
        $failure['capability'] = 'release_preparation';
        self::assertFalse(MachineResult::isValidPayload($failure, 4));
        $failure['verified_postconditions'] = ['prepared_run_projection_published'];
        self::assertFalse(MachineResult::isValidPayload($failure, 4));

        $conflict = $this->payload();
        $conflict['command'] = 'prepare';
        $conflict['capability'] = 'release_preparation';
        $conflict['status'] = 'conflict';
        $conflict['exit_class'] = 'refused';
        $conflict['exit_code'] = 23;
        self::assertFalse(MachineResult::isValidPayload($conflict, 23));
    }

    /**
     * Covers package validation: malformed fields and the closed input-failure and stop contracts.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_validation_rejects_malformed_fields_and_closed_stop_contracts(): void
    {
        $effectSet = [
            'schema_version' => 'fight-common.package-effect-set/v1',
            'effect_set_id'  => str_repeat('e', 64),
            'candidate_oid'  => str_repeat('c', 40),
            'version'        => '1.3.0',
            'archive_name'   => 'fight-common-v1.3.0.zip',
            'included_paths' => ['composer.json'],
            'excluded_paths' => []
        ];
        $success = [
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'package',
            'capability'              => 'release_packaging',
            'status'                  => 'packaged',
            'exit_class'              => 'success',
            'exit_code'               => 0,
            'plan_id'                 => str_repeat('a', 64),
            'run_id'                  => str_repeat('b', 64),
            'candidate_oid'           => str_repeat('c', 40),
            'archive_digest'          => str_repeat('d', 64),
            'effect_set'              => $effectSet,
            'artifacts'               => ['certification_handoff' => [
                'handoff_id' => str_repeat('f', 64),
                'path'       => '/runs/'.str_repeat('f', 64).'.certification-handoff.json'
            ]],
            'findings'                => [[
                'id'      => 'release.package.completed',
                'message' => 'The deterministic release archive was created and its identity was bound.'
            ]],
            'verified_postconditions' => [
                'phase_handoff_revalidated',
                'archive_created_and_verified'
            ],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'certify_release_package']
        ];

        self::assertTrue(MachineResult::isValidPayload($success, 0));

        $alreadySatisfied = $success;
        $alreadySatisfied['findings'] = [[
            'id'      => 'release.package.already_satisfied',
            'message' => 'The deterministic release archive already existed and was verified.'
        ]];
        $alreadySatisfied['verified_postconditions'] = [
            'phase_handoff_revalidated',
            'archive_already_persisted'
        ];
        self::assertTrue(MachineResult::isValidPayload($alreadySatisfied, 0));

        foreach (
            [
            ['plan_id' => 'invalid'],
            ['run_id' => 'invalid'],
            ['candidate_oid' => 'invalid'],
            ['archive_digest' => 'invalid'],
            ['effect_set' => 'invalid'],
            ['effect_set' => [...$effectSet, 'extra' => true]],
            ['effect_set' => [...$effectSet, 'included_paths' => 'invalid']],
            ['effect_set' => [...$effectSet, 'excluded_paths' => ['key' => 'value']]],
            ['effect_set' => [...$effectSet, 'schema_version' => 'wrong']]
            ] as $replacement
        ) {
            self::assertFalse(MachineResult::isValidPayload(array_replace($success, $replacement), 0));
        }

        $missingRequired = $success;
        unset($missingRequired['archive_digest']);
        self::assertFalse(MachineResult::isValidPayload($missingRequired, 0));

        $noPostconditions = $success;
        $noPostconditions['verified_postconditions'] = [];
        self::assertFalse(MachineResult::isValidPayload($noPostconditions, 0));

        $wrongNextAction = $success;
        $wrongNextAction['next_action'] = ['action' => 'package_release_run'];
        self::assertFalse(MachineResult::isValidPayload($wrongNextAction, 0));

        $missingEffectSet = $success;
        unset($missingEffectSet['effect_set']);
        self::assertFalse(MachineResult::isValidPayload($missingEffectSet, 0));

        $inputFailure = $this->payload();
        $inputFailure['command'] = 'package';
        $inputFailure['capability'] = 'release_packaging';
        $inputFailure['status'] = 'policy_blocked';
        $inputFailure['exit_class'] = 'invalid_input';
        $inputFailure['exit_code'] = 2;
        $inputFailure['findings'] = [[
            'id'      => 'release.package.handoff_forbidden',
            'message' => 'Packaging requires one phase handoff below the repository .runs directory.'
        ]];
        $inputFailure['next_action'] = ['action' => 'select_immutable_phase_handoff'];
        self::assertTrue(MachineResult::isValidPayload($inputFailure, 2));

        $unreadableInput = $inputFailure;
        $unreadableInput['findings'] = [[
            'id'      => 'release.package.handoff_unreadable',
            'message' => 'The phase handoff could not be read.'
        ]];
        self::assertTrue(MachineResult::isValidPayload($unreadableInput, 2));

        $invalidInput = $inputFailure;
        $invalidInput['findings'] = [[
            'id'      => 'release.package.handoff_invalid',
            'message' => 'The phase handoff failed canonical identity or binding revalidation.'
        ]];
        $invalidInput['next_action'] = ['action' => 'create_current_release_plan'];
        self::assertTrue(MachineResult::isValidPayload($invalidInput, 2));

        $derivationInput = $inputFailure;
        $derivationInput['findings'] = [[
            'id'      => 'release.package.effect_set_derivation_failed',
            'message' => 'The archive effect set could not be derived from the candidate commit.'
        ]];
        $derivationInput['next_action'] = ['action' => 'repair_release_repository_storage'];
        self::assertTrue(MachineResult::isValidPayload($derivationInput, 2));

        $approvalUnreadable = $inputFailure;
        $approvalUnreadable['findings'] = [[
            'id'      => 'release.package.approval_unreadable',
            'message' => 'The package approval could not be read.'
        ]];
        $approvalUnreadable['next_action'] = ['action' => 'provide_valid_package_approval'];
        self::assertTrue(MachineResult::isValidPayload($approvalUnreadable, 2));

        $approvalInvalid = $inputFailure;
        $approvalInvalid['findings'] = [[
            'id'      => 'release.package.approval_invalid',
            'message' => 'The package approval must be valid JSON.'
        ]];
        $approvalInvalid['next_action'] = ['action' => 'provide_valid_package_approval'];
        self::assertTrue(MachineResult::isValidPayload($approvalInvalid, 2));

        $unknownFinding = $inputFailure;
        $unknownFinding['findings'] = [[
            'id'      => 'release.package.unknown',
            'message' => 'Unknown package failure.'
        ]];
        self::assertFalse(MachineResult::isValidPayload($unknownFinding, 2));

        $nonStringFinding = $inputFailure;
        $nonStringFinding['findings'] = [['id' => 5, 'message' => 'Non-string finding.']];
        self::assertFalse(MachineResult::isValidPayload($nonStringFinding, 2));

        $refusal = $this->payload();
        $refusal['command'] = 'package';
        $refusal['capability'] = 'release_packaging';
        $refusal['status'] = 'authority_required';
        $refusal['exit_class'] = 'refused';
        $refusal['exit_code'] = 3;
        $refusal['plan_id'] = str_repeat('a', 64);
        $refusal['run_id'] = str_repeat('b', 64);
        $refusal['findings'] = [[
            'id'      => 'release.package.effect_set_refused',
            'message' => 'The packaging effect set was not approved for the exact bounded local effects.'
        ]];
        $refusal['next_action'] = ['action' => 'approve_exact_packaging_effects'];
        self::assertTrue(MachineResult::isValidPayload($refusal, 3));

        $archiveStops = [
            ['release.package.archive_creation_refused', 3, 'authority_required', 'refused', 'obtain_archive_creation_authority'],
            ['release.package.archive_creation_failed', 4, 'policy_blocked', 'failed', 'repair_archive_creation_provider'],
            ['release.package.archive_creation_uncertain', 5, 'evidence_indeterminate', 'uncertain', 'reconcile_archive_creation'],
            ['release.package.archive_creation_drift', 6, 'stale_plan', 'drifted', 'create_current_release_plan'],
            ['release.package.archive_creation_indeterminate', 5, 'evidence_indeterminate', 'uncertain', 'reconcile_archive_creation']
        ];

        foreach ($archiveStops as [$findingId, $exitCode, $status, $exitClass, $action]) {
            $stop = $this->payload();
            $stop['command'] = 'package';
            $stop['capability'] = 'release_packaging';
            $stop['status'] = $status;
            $stop['exit_class'] = $exitClass;
            $stop['exit_code'] = $exitCode;
            $stop['plan_id'] = str_repeat('a', 64);
            $stop['run_id'] = str_repeat('b', 64);
            $stop['findings'] = [[
                'id'      => $findingId,
                'message' => 'The archive boundary classified its stop.'
            ]];
            $stop['next_action'] = ['action' => $action];
            self::assertFalse(MachineResult::isValidPayload($stop, $exitCode), $findingId);
        }

        $unknownStop = $this->payload();
        $unknownStop['command'] = 'package';
        $unknownStop['capability'] = 'release_packaging';
        $unknownStop['status'] = 'policy_blocked';
        $unknownStop['exit_class'] = 'failed';
        $unknownStop['exit_code'] = 4;
        $unknownStop['plan_id'] = str_repeat('a', 64);
        $unknownStop['run_id'] = str_repeat('b', 64);
        $unknownStop['findings'] = [['id' => 'release.package.unknown_stop', 'message' => 'Unknown stop.']];
        $unknownStop['next_action'] = ['action' => 'reconcile_archive_creation'];
        self::assertFalse(MachineResult::isValidPayload($unknownStop, 4));
    }

    /**
     * Covers the closed non-success preparation contract as mutations of canonical factory results.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_preparation_stops_reject_every_cross_contract_mutation(): void
    {
        $factory = new ReleaseResultFactory();
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $manifestId = str_repeat('c', 64);
        $handoffId = str_repeat('d', 64);
        $artifacts = [
            'evidence_manifest' => [
                'manifest_id' => $manifestId,
                'path'        => '/repo/.runs/'.$manifestId.'.evidence-manifest.json'
            ],
            'phase_handoff'     => [
                'handoff_id' => $handoffId,
                'path'       => '/repo/.runs/'.$handoffId.'.phase-handoff.json'
            ]
        ];
        $stops = [
            'missing', 'conflict', 'stale', 'failed', 'create_conflict', 'state_indeterminate',
            'artifact_indeterminate', 'baseline_refusal', 'baseline_failure', 'baseline_uncertainty',
            'baseline_drift', 'baseline_missing', 'baseline_ambiguous', 'baseline_duplicate_normalized',
            'baseline_non_ancestor', 'support_policy_drift', 'approval_drift', 'evidence_drift',
            'compatibility_drift', 'authority_refused', 'authority_failed', 'authority_uncertain', 'indeterminate'
        ];

        foreach ($stops as $stop) {
            $effect = $this->preparationStopEffects($stop);
            $result = $factory->prepareResumeStop($stop, $planId, $runId, $artifacts, $effect);
            self::assertTrue(MachineResult::isValidPayload($result->payload, $result->exitCode), $stop);

            if (in_array($stop, ['missing', 'conflict', 'stale', 'state_indeterminate', 'indeterminate'], true)) {
                self::assertFalse(MachineResult::isValidPayload([
                    ...$result->payload,
                    'performed_effects' => [...$effect, [
                        'capability'   => 'git',
                        'effect_class' => 'git.resolve_ref',
                        'outcome'      => 'failure'
                    ]]
                ], $result->exitCode), $stop.':later_unrelated_failure');
            }

            if ($stop === 'baseline_failure') {
                self::assertFalse(MachineResult::isValidPayload([
                    ...$result->payload,
                    'performed_effects' => [...$effect, [
                        'capability'   => 'authorization',
                        'effect_class' => 'authorization.check',
                        'outcome'      => 'failure'
                    ]]
                ], $result->exitCode), $stop.':later_authority_failure');
            }

            if ($stop === 'authority_failed') {
                self::assertFalse(MachineResult::isValidPayload([
                    ...$result->payload,
                    'performed_effects' => [...$effect, [
                        'capability'   => 'git',
                        'effect_class' => 'git.resolve_ref',
                        'outcome'      => 'failure'
                    ]]
                ], $result->exitCode), $stop.':later_git_failure');
            }

            if ($stop === 'failed') {
                self::assertFalse(MachineResult::isValidPayload([
                    ...$result->payload,
                    'performed_effects' => [...$effect, [
                        'capability'   => 'hashing',
                        'effect_class' => 'hashing.sha256',
                        'outcome'      => 'failure'
                    ]]
                ], $result->exitCode), $stop.':later_hash_failure');
            }

            foreach (
                [
                    'finding'                   => ['findings' => [[
                        'id'      => 'release.prepare.unrelated',
                        'message' => 'Unrelated.'
                    ]]],
                    'message'                   => ['findings' => [[
                        'id'      => $result->payload['findings'][0]['id'],
                        'message' => 'Mutated.'
                    ]]],
                    'action'                    => ['next_action' => ['action' => 'unrelated_action']],
                    'postcondition'             => ['verified_postconditions' => ['prepared_run_projection_published']],
                    'proposal'                  => ['proposed_effects' => [['effect_class' => 'filesystem.write']]],
                    'empty_effects'             => ['performed_effects' => []],
                    'unrelated_effect'          => ['performed_effects' => [[
                        'capability'   => 'github',
                        'effect_class' => 'github.release',
                        'outcome'      => 'success'
                    ]]],
                    'wrong_causal_outcome'      => ['performed_effects' => $this->wrongCausalOutcome(
                        $effect,
                        $stop
                    )],
                    'appended_unrelated_effect' => ['performed_effects' => [...$effect, [
                        'capability'   => 'packagist',
                        'effect_class' => 'packagist.publish',
                        'outcome'      => 'success'
                    ]]],
                    'run_state'                 => ['run_state' => [
                        'history_path'    => '/repo/history.jsonl',
                        'projection_path' => '/repo/projection.json'
                    ]]
                ] as $mutation => $replacement
            ) {
                self::assertFalse(
                    MachineResult::isValidPayload(array_replace($result->payload, $replacement), $result->exitCode),
                    $stop.':'.$mutation
                );
            }
        }

        foreach (['/repo/./.runs', '/repo/cache/../.runs', '/repo//.runs'] as $nonCanonicalRoot) {
            $nonCanonicalArtifacts = $result->payload['artifacts'];
            $nonCanonicalArtifacts['evidence_manifest']['path'] = sprintf(
                '%s/%s.evidence-manifest.json',
                $nonCanonicalRoot,
                $manifestId
            );
            $nonCanonicalArtifacts['phase_handoff']['path'] = sprintf(
                '%s/%s.phase-handoff.json',
                $nonCanonicalRoot,
                $handoffId
            );
            self::assertFalse(MachineResult::isValidPayload([
                ...$result->payload,
                'artifacts' => $nonCanonicalArtifacts
            ], $result->exitCode), 'Artifact roots must be canonical: '.$nonCanonicalRoot);
        }

        $runtime = $factory->runtimeTermination('prepare');
        $normal = $factory->prepareResumeStop(
            'failed',
            $planId,
            $runId,
            $artifacts,
            $this->preparationStopEffects('failed')
        );
        self::assertFalse(MachineResult::isValidPayload([
            ...$normal->payload,
            'status'     => $runtime->payload['status'],
            'exit_class' => $runtime->payload['exit_class'],
            'exit_code'  => 71
        ], 71));
        self::assertFalse(MachineResult::isValidPayload([
            ...$runtime->payload,
            'findings'    => $normal->payload['findings'],
            'next_action' => $normal->payload['next_action']
        ], 71));
    }

    /**
     * Covers the exact pre-identity preparation failures admitted before a run can exist.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_preparation_input_failures_are_closed_factory_contracts(): void
    {
        $factory = new ReleaseResultFactory();
        $contracts = [
            'release.prepare.arguments_encoding_invalid' => [
                'Release command options must be valid UTF-8.',
                'provide_valid_utf8_arguments',
                null
            ],
            'release.prepare.ledger_unsupported'         => [
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'The command exposes its in-memory boundary ledger in the result and does not write ledger artifacts.',
                'read_performed_effects',
                null
            ],
            'release.prepare.inputs_required'            => [
                'Preparation requires exactly one immutable plan option.',
                'provide_prepare_plan',
                null
            ],
            'release.prepare.fixture_forbidden'          => [
                'Preparation fixtures are available only in the explicit direct-test runtime.',
                'remove_prepare_fixture',
                null
            ],
            'release.prepare.fixture_invalid'            => [
                'The controlled preparation fixture is invalid.',
                'provide_valid_prepare_fixture',
                null
            ],
            'release.prepare.authority_required'         => [
                'Normal preparation requires one current release-plan authority artifact.',
                'provide_current_release_plan_authority',
                null
            ],
            'release.prepare.plan_forbidden'             => [
                'Preparation requires one immutable plan below the repository .runs directory.',
                'select_immutable_release_plan',
                'filesystem.inspect_runs_directory'
            ],
            'release.prepare.plan_unreadable'            => [
                'The immutable release plan could not be read.',
                'select_immutable_release_plan',
                'filesystem.read'
            ],
            'release.prepare.plan_invalid'               => [
                'The immutable release plan failed canonical identity or binding revalidation.',
                'create_current_release_plan',
                'hashing.sha256'
            ],
            'release.prepare.run_identity_invalid'       => [
                'A unique release run identity could not be generated.',
                'retry_release_preparation',
                'hashing.sha256'
            ]
        ];

        foreach ($contracts as $findingId => [$message, $action, $effectClass]) {
            $effect = $effectClass === null ? [] : [[
                'capability'   => ReleaseEffect::from($effectClass)->capability(),
                'effect_class' => $effectClass,
                'outcome'      => 'success'
            ]];
            $result = $factory->failure('prepare', $findingId, $message, $action, $effect);
            self::assertTrue(MachineResult::isValidPayload($result->payload, 2), $findingId);

            foreach (
                [
                    ['findings' => [['id' => 'release.prepare.unrelated', 'message' => $message]]],
                    ['findings' => [['id' => $findingId, 'message' => 'Mutated.']]],
                    ['next_action' => ['action' => 'unrelated_action']],
                    ['verified_postconditions' => ['immutable_plan_revalidated']],
                    ['proposed_effects' => [['effect_class' => 'filesystem.write']]],
                    ['performed_effects' => [[
                        'capability'   => 'github',
                        'effect_class' => 'github.release',
                        'outcome'      => 'success'
                    ]]],
                    ['plan_id' => str_repeat('a', 64)]
                ] as $replacement
            ) {
                self::assertFalse(MachineResult::isValidPayload(
                    array_replace($result->payload, $replacement),
                    2
                ), $findingId.':'.json_encode($replacement));
            }

            if ($effect !== []) {
                self::assertFalse(MachineResult::isValidPayload([
                    ...$result->payload,
                    'performed_effects' => []
                ], 2), $findingId.':empty_effects');
            }
        }
    }

    /**
     * Covers both explicit artifactless evidence-exhaustion contracts and their closed mutations.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_evidence_persistence_failure_is_the_only_artifactless_identity_stop(): void
    {
        $factory = new ReleaseResultFactory();
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $failedEffects = [
            ...$this->preparationRevalidationEffects(),
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure']
        ];
        $failed = $factory->prepareEvidencePersistenceFailure($planId, $runId, $failedEffects);
        $persistedArtifactlessStop = $factory->prepareEvidencePersistenceFailure($planId, $runId, [
            ...$this->preparationRevalidationEffects(),
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
            ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
            ['capability' => 'authorization', 'effect_class' => 'authorization.check', 'outcome' => 'success'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure'],
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success']
        ]);
        $resumed = $factory->resumedPrepareEvidencePersistenceFailure(
            $planId,
            $runId,
            [
                'history_path'    => '/repo/runs/'.$runId.'/history.jsonl',
                'projection_path' => '/repo/runs/'.$runId.'/projection.json'
            ],
            $this->preparationRevalidationEffects()
        );

        self::assertTrue(MachineResult::isValidPayload($failed->payload, 5));
        self::assertTrue(MachineResult::isValidPayload($persistedArtifactlessStop->payload, 5));
        self::assertTrue(MachineResult::isValidPayload($resumed->payload, 5));
        self::assertFalse(MachineResult::isValidPayload([
            ...$persistedArtifactlessStop->payload,
            'performed_effects' => [...$persistedArtifactlessStop->payload['performed_effects'], [
                'capability'   => 'hashing',
                'effect_class' => 'hashing.sha256',
                'outcome'      => 'success'
            ]]
        ], 5));
        self::assertFalse(MachineResult::isValidPayload([
            ...$resumed->payload,
            'run_state' => [
                'history_path'    => '/repo/runs/'.$runId.'/history.jsonl',
                'projection_path' => '/other-root/runs/'.$runId.'/projection.json'
            ]
        ], 5));

        foreach (
            [
                ['findings' => [[
                    'id'      => 'release.prepare.unrelated',
                    'message' => 'Preparation evidence could not be durably persisted or reverified.'
                ]]],
                ['next_action' => ['action' => 'reconcile_named_release_run']],
                ['verified_postconditions' => ['unrelated_postcondition']],
                ['proposed_effects' => [['effect_class' => 'filesystem.write']]],
                ['performed_effects' => []],
                ['performed_effects' => [[
                    'capability' => 'github', 'effect_class' => 'github.release', 'outcome' => 'failure'
                ]]],
                ['artifacts' => [
                    'evidence_manifest' => ['manifest_id' => str_repeat('c', 64), 'path' => '/wrong'],
                    'phase_handoff'     => ['handoff_id' => str_repeat('d', 64), 'path' => '/wrong']
                ]]
            ] as $replacement
        ) {
            self::assertFalse(MachineResult::isValidPayload(array_replace($failed->payload, $replacement), 5));
        }

        foreach (
            [
                ['performed_effects' => []],
                ['performed_effects' => [[
                    'capability' => 'clock', 'effect_class' => 'clock.now', 'outcome' => 'success'
                ]]],
                ['performed_effects' => [[
                    'capability'   => 'filesystem',
                    'effect_class' => 'filesystem.inspect_runs_directory',
                    'outcome'      => 'failure'
                ], ...array_slice($resumed->payload['performed_effects'], 1)]]
            ] as $replacement
        ) {
            self::assertFalse(MachineResult::isValidPayload(array_replace($resumed->payload, $replacement), 5));
        }
    }

    /** @return list<array{capability: string, effect_class: string, outcome: string}> */
    private function preparationRevalidationEffects(): array
    {
        return [
            [
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.inspect_runs_directory',
                'outcome'      => 'success'
            ],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success']
        ];
    }

    /** @return list<array{capability: string, effect_class: string, outcome: string}> */
    private function artifactProofEffects(): array
    {
        return [
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
            ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success']
        ];
    }

    /** @return list<array{capability: string, effect_class: string, outcome: string}> */
    private function successfulPreparationEffects(bool $resumed): array
    {
        return [
            ...$this->preparationRevalidationEffects(),
            ...($resumed ? [
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success']
            ] : $this->artifactProofEffects()),
            ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
            ['capability' => 'authorization', 'effect_class' => 'authorization.check', 'outcome' => 'success']
        ];
    }

    /** @return list<array{capability: string, effect_class: string, outcome: string}> */
    private function preparationStopEffects(string $stop): array
    {
        $causal = match ($stop) {
            'missing', 'stale', 'indeterminate' => ['filesystem', 'filesystem.read', 'uncertainty'],
            'conflict' => ['filesystem', 'filesystem.read', 'refusal'],
            'failed' => ['filesystem', 'filesystem.write', 'failure'],
            'create_conflict' => ['filesystem', 'filesystem.write', 'refusal'],
            'state_indeterminate' => ['filesystem', 'filesystem.write', 'uncertainty'],
            'artifact_indeterminate' => ['filesystem', 'filesystem.write', 'failure'],
            'baseline_refusal' => ['git', 'git.resolve_ref', 'refusal'],
            'baseline_failure' => ['git', 'git.resolve_ref', 'failure'],
            'baseline_uncertainty' => ['git', 'git.resolve_ref', 'uncertainty'],
            'baseline_drift' => ['git', 'git.resolve_ref', 'drift'],
            'baseline_missing', 'baseline_ambiguous', 'baseline_duplicate_normalized',
            'baseline_non_ancestor' => ['git', 'git.resolve_ref', 'success'],
            'authority_refused' => ['authorization', 'authorization.check', 'refusal'],
            'authority_failed' => ['authorization', 'authorization.check', 'failure'],
            'authority_uncertain' => ['authorization', 'authorization.check', 'uncertainty'],
            'support_policy_drift', 'approval_drift', 'evidence_drift',
            'compatibility_drift' => ['authorization', 'authorization.check', 'success'],
            default => null
        };
        $effects = $this->preparationRevalidationEffects();

        if ($causal !== null) {
            $effects[] = ['capability' => $causal[0], 'effect_class' => $causal[1], 'outcome' => $causal[2]];
        }

        return [...$effects, ...$this->artifactProofEffects()];
    }

    /**
     * @param list<array{capability: string, effect_class: string, outcome: string}> $effects
     *
     * @return list<array{capability: string, effect_class: string, outcome: string}>
     */
    private function wrongCausalOutcome(array $effects, string $stop): array
    {
        [$effectClass, $outcome] = match ($stop) {
            'missing', 'stale', 'indeterminate' => ['filesystem.read', 'uncertainty'],
            'conflict' => ['filesystem.read', 'refusal'],
            'failed' => ['filesystem.write', 'failure'],
            'create_conflict' => ['filesystem.write', 'refusal'],
            'state_indeterminate' => ['filesystem.write', 'uncertainty'],
            'artifact_indeterminate' => ['filesystem.write', 'failure'],
            'baseline_refusal' => ['git.resolve_ref', 'refusal'],
            'baseline_failure' => ['git.resolve_ref', 'failure'],
            'baseline_uncertainty' => ['git.resolve_ref', 'uncertainty'],
            'baseline_drift' => ['git.resolve_ref', 'drift'],
            'baseline_missing', 'baseline_ambiguous', 'baseline_duplicate_normalized',
            'baseline_non_ancestor' => ['git.resolve_ref', 'success'],
            'authority_refused' => ['authorization.check', 'refusal'],
            'authority_failed' => ['authorization.check', 'failure'],
            'authority_uncertain' => ['authorization.check', 'uncertainty'],
            default => ['authorization.check', 'success']
        };
        $index = array_find_key(
            $effects,
            static fn (array $effect): bool => $effect['effect_class'] === $effectClass
                && $effect['outcome'] === $outcome
        );
        assert(is_int($index));
        $effects[$index]['outcome'] = 'already_satisfied';

        return $effects;
    }

    /**
     * Covers fail-closed construction.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_construction_rejects_an_incomplete_contract(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MachineResult(['schema_version' => 'fight-common.release-result/v1'], 4);
    }

    /**
     * Covers the closed success and fail-closed shapes for compatibility evidence.
     */
    public function test_that_compatibility_results_require_complete_read_only_evidence(): void
    {
        $success = $this->compatibilityPayload();
        self::assertTrue(MachineResult::isValidPayload($success, 0));
        self::assertSame(0, new MachineResult($success, 0)->exitCode);

        $failure = $this->payload();
        $failure['command'] = 'compatibility';
        $failure['capability'] = 'compatibility_assessment';
        $failure['status'] = 'evidence_indeterminate';
        $failure['exit_class'] = 'uncertain';
        $failure['exit_code'] = 5;
        self::assertTrue(MachineResult::isValidPayload($failure, 5));

        $invalid = $success;
        $invalid['evidence']['consumer']['lock']['sha256'] = 'untrusted';
        self::assertFalse(MachineResult::isValidPayload($invalid, 0));

        $invalid = $success;
        $invalid['evidence'] = [];
        self::assertFalse(MachineResult::isValidPayload($invalid, 0));
    }

    /**
     * Covers acceptance of one exact attributed compatibility-authority failure at the public result seam.
     */
    public function test_that_compatibility_accepts_an_authenticated_attributed_failure_finding(): void
    {
        $result = new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'compatibility',
            'capability'              => 'compatibility_assessment',
            'status'                  => 'evidence_indeterminate',
            'exit_class'              => 'uncertain',
            'findings'                => [[
                'id'          => 'release.compatibility.structural-api.missing-classification',
                'message'     => 'Structural compatibility authority rejected the evidence.',
                'attribution' => 'compatibility-manifest',
                'subject'     => 'Fight\\Common\\Domain\\Value\\UnclassifiedValue',
                'operation'   => null
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'restore_manifest_evidence_and_retry']
        ], 5);

        self::assertSame(5, $result->exitCode);
        self::assertSame([
            'id'          => 'release.compatibility.structural-api.missing-classification',
            'message'     => 'Structural compatibility authority rejected the evidence.',
            'attribution' => 'compatibility-manifest',
            'subject'     => 'Fight\\Common\\Domain\\Value\\UnclassifiedValue',
            'operation'   => null
        ], $result->payload['findings'][0]);
        self::assertSame(
            ['action' => 'restore_manifest_evidence_and_retry'],
            $result->payload['next_action']
        );
    }

    /**
     * Covers fail-closed construction of semantically incomplete successful results.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_construction_rejects_success_without_command_specific_evidence(): void
    {
        $inspection = $this->inspectionPayload();
        unset($inspection['recommendation']);

        $plan = $this->payload();
        $plan['command'] = 'plan';
        $plan['capability'] = 'release_planning';
        $plan['status'] = 'succeeded';
        $plan['exit_class'] = 'success';

        foreach ([$inspection, $plan] as $payload) {
            try {
                new MachineResult($payload, 0);
                self::fail('Incomplete successful machine results must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(
                    'The release machine result does not satisfy the v1 contract.',
                    $exception->getMessage()
                );
            }
        }
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'inspect',
            'capability'              => 'release_inspection',
            'status'                  => 'policy_blocked',
            'exit_class'              => 'failed',
            'exit_code'               => 4,
            'findings'                => [['id' => 'release.test.failure', 'message' => 'Synthetic failure.']],
            'verified_postconditions' => [],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'repair_test_failure']
        ];
    }

    /** @return array<string, mixed> */
    private function inspectionPayload(): array
    {
        return [
            ...$this->payload(),
            'status'                  => 'succeeded',
            'exit_class'              => 'success',
            'exit_code'               => 0,
            'resolved_inputs'         => [
                'source_commit'       => str_repeat('c', 40),
                'baseline_tag'        => 'v1.0.0',
                'baseline_tag_object' => str_repeat('a', 40),
                'baseline_commit'     => str_repeat('b', 40),
                'support_policy'      => 'supported'
            ],
            'recommendation'          => [
                'minimum_increment'        => 'patch',
                'recommended_version'      => '1.0.1',
                'authoritative'            => false,
                'compatibility_assessment' => [
                    'categories' => array_map(
                        static fn (string $category): array => [
                            'category'       => $category,
                            'finding_id'     => 'release.compatibility.'.$category.'.fixture',
                            'evidence_id'    => 'evidence.compatibility.'.$category.'.fixture',
                            'classification' => 'patch'
                        ],
                        CompatibilityAssessment::CATEGORIES
                    ),
                    'rationale'  => 'maximum_required_increment_across_all_compatibility_categories'
                ]
            ],
            'verified_postconditions' => ['minimum_increment_recommendation_derived'],
            'next_action'             => ['action' => 'approve_exact_version_for_plan', 'version' => '1.0.1']
        ];
    }

    /** @return array<string, mixed> */
    private function compatibilityPayload(): array
    {
        return [
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'compatibility',
            'capability'              => 'compatibility_assessment',
            'status'                  => 'succeeded',
            'exit_class'              => 'success',
            'exit_code'               => 0,
            'findings'                => [[
                'id'      => 'release.compatibility.harness-completed',
                'message' => 'Repository-owned compatibility evidence completed without certifying release.'
            ]],
            'verified_postconditions' => [
                'compatibility_manifest_authenticated',
                'structural_evidence_composed',
                'disposable_public_consumer_verified'
            ],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'review_compatibility_evidence'],
            'evidence'                => [
                'manifest'   => ['status' => 'valid', 'baseline' => ['version' => '1.1.0']],
                'structural' => ['status' => 'valid', 'classification' => 'minor', 'findings' => []],
                'consumer'   => [
                    'schema_version'   => 'fight-common.disposable-public-consumer/v1',
                    'status'           => 'valid',
                    'resolved_package' => ['installed_as' => 'copy'],
                    'lock'             => ['sha256' => str_repeat('a', 64)]
                ]
            ]
        ];
    }

    /** @return array<string, mixed> */
    private function runtimeBootstrapPayload(
        string $command = 'inspect',
        string $capability = 'release_inspection'
    ): array {
        return [
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => $command,
            'capability'              => $capability,
            'status'                  => 'infrastructure_unavailable',
            'exit_class'              => 'failed',
            'exit_code'               => 70,
            'findings'                => [[
                'id'      => 'release.runtime.bootstrap_unavailable',
                'message' => 'The canonical release runtime could not be started.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'restore_release_runtime_and_retry']
        ];
    }
}

// phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
