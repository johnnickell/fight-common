<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release;

use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Common\Application\Release\Boundary\ReleaseEffectLedger;

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
        [$command, $capability] = match ($requestedCommand) {
            'inspect' => ['inspect', 'release_inspection'],
            'plan' => ['plan', 'release_planning'],
            default => ['unknown', 'unsupported_command'],
        };

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
        [$command, $capability] = match ($requestedCommand) {
            'inspect' => ['inspect', 'release_inspection'],
            'plan' => ['plan', 'release_planning'],
            default => ['unknown', 'unsupported_command'],
        };

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
        $capability = match ($command) {
            'inspect' => 'release_inspection',
            'plan' => 'release_planning',
            default => 'unsupported_command',
        };

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
}
