<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Observation;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Class RecordPhaseObservation
 */
final readonly class RecordPhaseObservation implements Command
{
    /**
     * Constructs RecordPhaseObservation
     */
    public function __construct(public Observation $observation)
    {
        if ($observation->phase === null) {
            throw new DomainException('Expected a phase observation.');
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(Observation::fromArray($data));
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->observation->toArray();
    }
}
