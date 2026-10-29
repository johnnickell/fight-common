<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Detail\Data;

use Fight\Common\Domain\Mcp\Detail\CaptureCompleteness;
use Fight\Common\Domain\Mcp\Detail\DetailCapture;
use Fight\Common\Domain\Mcp\Observation\Observation;

/**
 * Class DetailReference
 *
 * Trusted permitted metadata assembly, never a body-read grant
 */
final readonly class DetailReference
{
    /**
     * Constructs DetailReference
     */
    public function __construct(private Observation $observation, private DetailCapture $capture, private bool $present)
    {
    }

    /**
     * Returns supplied capture and observed storage availability without protected bytes
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $availability = $this->capture->completeness()->value;
        if (
            in_array(
                $this->capture->completeness(),
                [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL],
                true
            )
        ) {
            $availability = $this->present ? 'retained' : 'missing';
        }

        return [
            'producer_id'    => $this->observation->producerId(),
            'observation_id' => $this->observation->id()->toString(),
            'occurred_at'    => $this->observation->occurrence()?->toString(),
            'recorded_at'    => $this->observation->recordedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'capture'        => $this->capture->toArray(),
            'availability'   => $availability
        ];
    }
}
