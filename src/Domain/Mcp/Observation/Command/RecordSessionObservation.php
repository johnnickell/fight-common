<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Observation\Command;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Class RecordSessionObservation
 */
final readonly class RecordSessionObservation implements Command
{
    /**
     * Constructs RecordSessionObservation
     */
    public function __construct(private Observation $observation)
    {
        if ($observation->phase() !== null) {
            throw new DomainException('Expected a session observation.');
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
     * Returns the session observation
     */
    public function observation(): Observation
    {
        return $this->observation;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->observation->toArray();
    }
}
