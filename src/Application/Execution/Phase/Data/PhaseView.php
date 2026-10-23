<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Phase\Data;

use Fight\Common\Application\Execution\Observation\Data\ObservationView;
use Fight\Common\Domain\Execution\Phase\PhaseReference;

/**
 * Class PhaseView
 */
final readonly class PhaseView
{
    /**
     * Constructs PhaseView
     *
     * @phpstan-param list<string> $models
     */
    public function __construct(
        private PhaseReference $phase,
        private ?ObservationView $observation,
        private ?string $latestRecordedAt,
        private array $models
    ) {
    }

    /**
     * Returns the phase with only a currently permitted consistent parent reference
     */
    public function phase(): PhaseReference
    {
        return $this->phase;
    }

    /**
     * Returns safe phase evidence without activity text or protected detail
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'phase'              => $this->phase->toArray(),
            'observation'        => $this->observation?->toArray(),
            'latest_recorded_at' => $this->latestRecordedAt,
            'models'             => $this->models
        ];
    }
}
