<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Session\Data;

use Fight\Common\Application\Mcp\Execution\Observation\Data\ObservationView;

/**
 * Class ExecutionInspection
 */
final readonly class ExecutionInspection
{
    /**
     * Constructs ExecutionInspection
     */
    public function __construct(
        private ExecutionView $execution,
        private ?ObservationView $observation,
        private ExecutionSummary $summary
    ) {
    }

    /**
     * Returns selected safe evidence and full authorized execution statistics
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'execution'   => $this->execution->toArray(),
            'observation' => $this->observation?->toArray(),
            'summary'     => $this->summary->toArray()
        ];
    }
}
