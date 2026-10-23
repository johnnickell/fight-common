<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Session\Data;

use Fight\Common\Domain\Execution\Usage\UsageTotal;

/**
 * Class ExecutionSummary
 */
final readonly class ExecutionSummary
{
    /**
     * Constructs ExecutionSummary
     *
     * @phpstan-param array<string, int> $outcomes
     * @phpstan-param list<UsageTotal> $usage
     */
    public function __construct(
        private int $executions,
        private array $outcomes,
        private int $activities,
        private int $activitiesWithoutUsage,
        private array $usage,
        private ?string $latestRecordedAt
    ) {
    }

    /**
     * Returns full-selection persisted evidence without claiming complete capture
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'executions'               => $this->executions,
            'outcomes'                 => $this->outcomes,
            'activities'               => $this->activities,
            'activities_without_usage' => $this->activitiesWithoutUsage,
            'usage'                    => array_map(
                static fn (UsageTotal $total): array => $total->toArray(),
                $this->usage
            ),
            'latest_recorded_at'       => $this->latestRecordedAt,
            'capture_completeness'     => 'unknown'
        ];
    }
}
