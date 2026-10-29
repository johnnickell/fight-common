<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Observation\Data;

use Fight\Common\Domain\Mcp\Observation\Observation;

/**
 * Class ObservationView
 *
 * Safe observed state evidence, not a Workflow verdict or activity response
 */
final readonly class ObservationView
{
    /**
     * @var array<string, string|null>
     */
    private array $data;

    /**
     * Constructs ObservationView
     */
    public function __construct(Observation $observation)
    {
        $this->data = [
            'observation_id' => $observation->id()->toString(),
            'producer_id'    => $observation->producerId(),
            'occurred_at'    => $observation->occurredAt()->format('Y-m-d\TH:i:s.u\Z'),
            'recorded_at'    => $observation->recordedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'started_at'     => $observation->startedAt()?->format('Y-m-d\TH:i:s.u\Z'),
            'ended_at'       => $observation->endedAt()?->format('Y-m-d\TH:i:s.u\Z'),
            'outcome'        => $observation->outcome()->value
        ];
    }

    /**
     * Returns only timing, outcome and recording provenance
     *
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
