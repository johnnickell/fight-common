<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Session\Data;

/**
 * Class ExecutionPage
 */
final readonly class ExecutionPage
{
    /**
     * Constructs ExecutionPage
     *
     * @phpstan-param list<ExecutionView> $executions
     */
    public function __construct(private array $executions, private int $total, private int $page, private int $perPage)
    {
    }

    /**
     * Returns the bounded typed execution rows
     *
     * @return list<ExecutionView>
     */
    public function executions(): array
    {
        return $this->executions;
    }

    /**
     * Returns the live page and separately read permitted total
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'executions' => array_map(static fn (ExecutionView $row): array => $row->toArray(), $this->executions),
            'total'      => $this->total,
            'page'       => $this->page,
            'per_page'   => $this->perPage
        ];
    }
}
