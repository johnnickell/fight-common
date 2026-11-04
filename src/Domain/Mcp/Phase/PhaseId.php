<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Phase;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class PhaseId
 *
 * Exact project/phase tuple; session and parent are immutable relationships
 */
final readonly class PhaseId extends ValueObject
{
    /**
     * Constructs PhaseId
     */
    private function __construct(private ObservationReference $project, private ObservationReference $phase)
    {
    }

    /**
     * Creates an exact project-scoped phase identity
     */
    public static function fromStrings(string $projectId, string $phaseId): self
    {
        return new self(ObservationReference::fromString($projectId), ObservationReference::fromString($phaseId));
    }

    /**
     * Creates an identity from stable tuple text without delimiter ambiguity
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data) || !array_is_list($data) || count($data) !== 2) {
            throw new DomainException('Invalid phase identity.');
        }

        return new self(ObservationReference::fromString($data[0]), ObservationReference::fromString($data[1]));
    }

    /**
     * Returns the exact project reference
     */
    public function projectId(): string
    {
        return $this->project->toString();
    }

    /**
     * Returns the exact local phase reference
     */
    public function phaseId(): string
    {
        return $this->phase->toString();
    }

    /**
     * Returns stable tuple text without normalizing component bytes
     */
    public function toString(): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the identity tuple, not the complete phase relationship
     *
     * @return array{string, string}
     */
    public function jsonSerialize(): array
    {
        return [$this->projectId(), $this->phaseId()];
    }
}
