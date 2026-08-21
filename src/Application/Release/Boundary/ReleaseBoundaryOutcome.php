<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

/**
 * Enum ReleaseBoundaryOutcome
 *
 * Owns the closed vocabulary and machine classification for normally completed boundary outcomes.
 */
enum ReleaseBoundaryOutcome: string
{
    case SUCCESS = 'success';
    case ALREADY_SATISFIED = 'already_satisfied';
    case REFUSAL = 'refusal';
    case FAILURE = 'failure';
    case UNCERTAINTY = 'uncertainty';
    case DRIFT = 'drift';

    /**
     * Returns the stable machine classification for this outcome
     *
     * @return array{status: string, exit_class: string, exit_code: int, next_action: string}
     */
    public function classification(): array
    {
        return match ($this) {
            self::SUCCESS => [
                'status'      => 'succeeded',
                'exit_class'  => 'success',
                'exit_code'   => 0,
                'next_action' => 'continue_release_planning'
            ],
            self::ALREADY_SATISFIED => [
                'status'      => 'evidence_indeterminate',
                'exit_class'  => 'uncertain',
                'exit_code'   => 5,
                'next_action' => 'verify_boundary_postcondition'
            ],
            self::REFUSAL => [
                'status'      => 'authority_required',
                'exit_class'  => 'refused',
                'exit_code'   => 3,
                'next_action' => 'obtain_boundary_authority'
            ],
            self::FAILURE => [
                'status'      => 'policy_blocked',
                'exit_class'  => 'failed',
                'exit_code'   => 4,
                'next_action' => 'repair_boundary_failure'
            ],
            self::UNCERTAINTY => [
                'status'      => 'evidence_indeterminate',
                'exit_class'  => 'uncertain',
                'exit_code'   => 5,
                'next_action' => 'reconcile_boundary_effect'
            ],
            self::DRIFT => [
                'status'      => 'stale_plan',
                'exit_class'  => 'drifted',
                'exit_code'   => 6,
                'next_action' => 'refresh_bound_inputs'
            ]
        };
    }
}
