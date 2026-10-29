<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Observation\Data;

/**
 * Class ActivityPage
 */
final readonly class ActivityPage
{
    /**
     * Constructs ActivityPage
     *
     * @phpstan-param list<ActivityView> $activities
     */
    public function __construct(private array $activities, private int $total, private int $page, private int $perPage)
    {
    }

    /**
     * Returns bounded typed safe activity rows
     *
     * @return list<ActivityView>
     */
    public function activities(): array
    {
        return $this->activities;
    }

    /**
     * Returns the live activity page without concealed reference counts
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'activities' => array_map(static fn (ActivityView $view): array => $view->toArray(), $this->activities),
            'total'      => $this->total,
            'page'       => $this->page,
            'per_page'   => $this->perPage
        ];
    }
}
