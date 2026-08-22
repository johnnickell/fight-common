<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;

/**
 * Class ReleasePlanCapabilityFirewall
 *
 * Rejects malformed or forbidden planning effects before any release boundary operation.
 */
final readonly class ReleasePlanCapabilityFirewall
{
    /**
     * Constructs ReleasePlanCapabilityFirewall
     */
    public function __construct(private ReleaseResultFactory $results)
    {
    }

    /**
     * Returns a capability stop, or null when the candidate may enter planning orchestration
     *
     * @param array<string, mixed> $candidate Candidate plan input.
     */
    public function inspect(array $candidate): ?MachineResult
    {
        $boundary = $candidate['boundary'] ?? null;

        if ($boundary === null) {
            return null;
        }

        if (
            !is_array($boundary)
            || !is_string($boundary['effect_class'] ?? null)
            || !is_string($boundary['outcome'] ?? null)
        ) {
            return $this->results->failure(
                'plan',
                'release.boundary.fixture_invalid',
                'The planning fixture does not declare one controlled effect and outcome.',
                'correct_boundary_fixture',
                []
            );
        }

        if ($boundary['effect_class'] !== 'filesystem.write') {
            return $this->results->failure(
                'plan',
                'release.capability.effect_forbidden',
                'Planning cannot perform the requested effect class.',
                'select_permitted_capability',
                []
            );
        }

        $outcome = ReleaseBoundaryOutcome::tryFrom($boundary['outcome']);

        if (
            $boundary['outcome'] !== 'crash'
            && ($outcome === null || $outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED)
        ) {
            return $this->results->failure(
                'plan',
                'release.boundary.outcome_unsupported',
                'The planning fixture does not declare a supported deterministic outcome.',
                'correct_boundary_fixture',
                []
            );
        }

        return null;
    }
}
