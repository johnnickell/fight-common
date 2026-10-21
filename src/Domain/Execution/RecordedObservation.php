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
    public DateTimeImmutable $recordedAt;

    /**
     * Constructs RecordedObservation
     */
    public function __construct(public Observation $observation, DateTimeImmutable $recordedAt)
    {
        $this->recordedAt = Observation::timestamp(
            $recordedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z')
        );
    }
}
