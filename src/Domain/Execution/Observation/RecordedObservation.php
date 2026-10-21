<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Observation;

use DateTimeImmutable;

/**
 * Class RecordedObservation
 *
 * Receipt of a recorded Observation root, with no separate identity or persistence
 */
final readonly class RecordedObservation
{
    private Observation $observation;

    /**
     * Constructs RecordedObservation
     */
    public function __construct(Observation $observation, DateTimeImmutable $recordedAt)
    {
        $this->observation = $observation->recorded($recordedAt);
    }

    /**
     * Returns the recorded observation root
     */
    public function observation(): Observation
    {
        return $this->observation;
    }

    /**
     * Returns the root's server recording time
     */
    public function recordedAt(): DateTimeImmutable
    {
        return $this->observation->recordedAt();
    }
}
