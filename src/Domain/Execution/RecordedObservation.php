<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Class RecordedObservation
 */
final readonly class RecordedObservation
{
    private DateTimeImmutable $recordedAt;

    /**
     * Constructs RecordedObservation
     */
    public function __construct(private Observation $observation, DateTimeImmutable $recordedAt)
    {
        $this->recordedAt = Observation::timestamp(
            $recordedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z')
        );
    }

    /**
     * Returns the submitted observation
     */
    public function observation(): Observation
    {
        return $this->observation;
    }

    /**
     * Returns the server recording time
     */
    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
