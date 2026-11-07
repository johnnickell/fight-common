<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Phase;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class PhaseReference
 */
final readonly class PhaseReference extends ValueObject
{
    /**
     * Constructs PhaseReference
     */
    private function __construct(
        private string $sessionId,
        private string $phaseId,
        private ?string $parentPhaseId,
        private string $agentId,
        private string $name,
        private int $attempt
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
            ObservationReference::fromString($sessionId)->toString(),
            ObservationReference::fromString($phaseId)->toString(),
            $parentPhaseId === null ? null : ObservationReference::fromString($parentPhaseId)->toString(),
            ObservationReference::fromString($agentId)->toString(),
            ObservationReference::fromString($name)->toString(),
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
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['agent_id', 'attempt', 'name', 'parent_phase_id', 'phase_id', 'session_id']) {
            throw new DomainException('Invalid phase reference structure.');
        }

        if (!is_int($data['attempt'])) {
            throw new DomainException('Invalid phase attempt.');
        }

        $parentPhaseId = null;
        if ($data['parent_phase_id'] !== null) {
            $parentPhaseId = ObservationReference::fromString($data['parent_phase_id'])->toString();
        }

        return self::fromStrings(
            ObservationReference::fromString($data['session_id'])->toString(),
            ObservationReference::fromString($data['phase_id'])->toString(),
            $parentPhaseId,
            ObservationReference::fromString($data['agent_id'])->toString(),
            ObservationReference::fromString($data['name'])->toString(),
            $data['attempt']
        );
    }

    /**
     * Creates a phase reference from stable structured text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid phase reference text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns stable structured reference text
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns structured reference data
     *
     * @return array<string, string|int|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the session reference
     */
    public function sessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Returns the phase reference
     */
    public function phaseId(): string
    {
        return $this->phaseId;
    }

    /**
     * Returns the optional parent phase reference
     */
    public function parentPhaseId(): ?string
    {
        return $this->parentPhaseId;
    }

    /**
     * Returns the performing Agent reference
     */
    public function agentId(): string
    {
        return $this->agentId;
    }

    /**
     * Returns the phase name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the reported attempt
     */
    public function attempt(): int
    {
        return $this->attempt;
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
