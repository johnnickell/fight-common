<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Phase\Data;

/**
 * Class PhasePage
 */
final readonly class PhasePage
{
    /**
     * Constructs PhasePage
     *
     * @phpstan-param list<PhaseView> $phases
     */
    public function __construct(private array $phases, private int $total, private int $page, private int $perPage)
    {
    }

    /**
     * Returns the bounded typed phase rows
     *
     * @return list<PhaseView>
     */
    public function phases(): array
    {
        return $this->phases;
    }

    /**
     * Returns the live page and separately read authorized total
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'phases'   => array_map(static fn (PhaseView $phase): array => $phase->toArray(), $this->phases),
            'total'    => $this->total,
            'page'     => $this->page,
            'per_page' => $this->perPage
        ];
    }
}
