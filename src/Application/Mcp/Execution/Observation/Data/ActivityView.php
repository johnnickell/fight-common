<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Observation\Data;

use Fight\Common\Application\Mcp\Execution\Detail\Data\DetailReference;
use Fight\Common\Domain\Mcp\Activity\PhaseActivity;
use Fight\Common\Domain\Mcp\Observation\Observation;

/**
 * Class ActivityView
 */
final readonly class ActivityView
{
    /**
     * Constructs ActivityView
     *
     * @phpstan-param list<DetailReference> $details
     */
    public function __construct(
        private Observation $observation,
        private PhaseActivity $activity,
        private array $details
    ) {
    }

    /**
     * Returns permitted detail references without authority to retrieve bodies
     *
     * @return list<DetailReference>
     */
    public function details(): array
    {
        return $this->details;
    }

    /**
     * Returns only consumer-safe activity and permitted capture metadata
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'producer_id'    => $this->observation->producerId(),
            'observation_id' => $this->observation->id()->toString(),
            'occurred_at'    => $this->observation->occurrence()?->toString(),
            'recorded_at'    => $this->observation->recordedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'agent_id'       => $this->observation->phase()?->agentId(),
            'activity'       => $this->activity->toArray(),
            'details'        => array_map(
                static fn (DetailReference $detail): array => $detail->toArray(),
                $this->details
            )
        ];
    }
}
