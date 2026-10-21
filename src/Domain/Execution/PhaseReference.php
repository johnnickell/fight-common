<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class PhaseReference
 */
final readonly class PhaseReference
{
    /**
     * Constructs PhaseReference
     */
    private function __construct(
        public string $sessionId,
        public string $phaseId,
        public ?string $parentPhaseId,
        public string $agentId,
        public string $name,
        public int $attempt
    ) {
    }

    /**
     * Creates a validated phase reference
     */
    public static function fromStrings(
        string $sessionId,
        string $phaseId,
        ?string $parentPhaseId,
        string $agentId,
        string $name,
        int $attempt
    ): self {
        if (
            $attempt < 1 || $attempt > 1000000 || strlen($name) > 64
            || ($parentPhaseId !== null && $parentPhaseId === $phaseId)
        ) {
            throw new DomainException('Invalid phase reference.');
        }

        return new self(
            ObservationFields::reference($sessionId),
            ObservationFields::reference($phaseId),
            $parentPhaseId === null ? null : ObservationFields::reference($parentPhaseId),
            ObservationFields::reference($agentId),
            ObservationFields::reference($name),
            $attempt
        );
    }

    /**
     * Creates a phase reference from structured data
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        ObservationFields::structure($data, [
            'session_id', 'phase_id', 'parent_phase_id', 'agent_id', 'name', 'attempt'
        ]);
        if (!is_int($data['attempt'])) {
            throw new DomainException('Invalid phase attempt.');
        }

        return self::fromStrings(
            ObservationFields::reference($data['session_id']),
            ObservationFields::reference($data['phase_id']),
            $data['parent_phase_id'] === null ? null : ObservationFields::reference($data['parent_phase_id']),
            ObservationFields::reference($data['agent_id']),
            ObservationFields::reference($data['name']),
            $data['attempt']
        );
    }

    /**
     * Returns the structured reference
     *
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'session_id'      => $this->sessionId,
            'phase_id'        => $this->phaseId,
            'parent_phase_id' => $this->parentPhaseId,
            'agent_id'        => $this->agentId,
            'name'            => $this->name,
            'attempt'         => $this->attempt
        ];
    }
}
