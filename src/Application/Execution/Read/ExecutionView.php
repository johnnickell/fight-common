<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Read;

use Fight\Common\Domain\Execution\Observation\ObservationOutcome;
use Fight\Common\Domain\Execution\Session\SessionReference;

/**
 * Class ExecutionView
 */
final readonly class ExecutionView
{
    /**
     * Constructs ExecutionView
     */
    public function __construct(
        private SessionReference $session,
        private ObservationOutcome $outcome,
        private ?string $latestRecordedAt
    ) {
    }

    /**
     * Returns the safe session reference
     */
    public function session(): SessionReference
    {
        return $this->session;
    }

    /**
     * Returns the latest retained session observation outcome, never Workflow state
     */
    public function outcome(): ObservationOutcome
    {
        return $this->outcome;
    }

    /**
     * Returns the maximum retained recording time for this execution
     */
    public function latestRecordedAt(): ?string
    {
        return $this->latestRecordedAt;
    }

    /**
     * Returns only safe reference, outcome and persisted freshness data
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session'            => $this->session->toArray(),
            'outcome'            => $this->outcome->value,
            'latest_recorded_at' => $this->latestRecordedAt
        ];
    }
}
