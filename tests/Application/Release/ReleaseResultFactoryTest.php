<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Release;

use Fight\Common\Adapter\Release\Fake\DeterministicReleaseBoundaryFake;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Common\Application\Release\ReleasePlanValidationFailure;
use Fight\Common\Application\Release\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
#[CoversClass(ReleaseResultFactory::class)]
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
            'inspect' => ['inspect', 'release_inspection'],
            'plan'    => ['plan', 'release_planning'],
            'publish' => ['unknown', 'unsupported_command']
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
            'inspect' => ['inspect', 'release_inspection'],
            'plan'    => ['plan', 'release_planning'],
            'publish' => ['unknown', 'unsupported_command']
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
}

// phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
