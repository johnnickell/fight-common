<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Release;

use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Common\Application\Release\Boundary\ReleaseEffect;
use Fight\Common\Application\Release\CompatibilityAssessment;
use Fight\Common\Application\Release\MachineResult;
use Fight\Common\Application\Release\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
#[CoversClass(MachineResult::class)]
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
            'inspect' => 'release_inspection',
            'plan'    => 'release_planning',
            'unknown' => 'unsupported_command'
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
