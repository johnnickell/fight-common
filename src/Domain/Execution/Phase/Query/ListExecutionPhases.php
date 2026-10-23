<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Phase\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Session\Query\GetExecution;
use Fight\Common\Domain\Execution\Value\ObservationFields;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Repository\Pagination;

/**
 * Class ListExecutionPhases
 */
final readonly class ListExecutionPhases implements Query
{
    private Pagination $pagination;
    private GetExecution $execution;
    /**
     * @var array<string, string>
     */
    private array $filters;

    /**
     * Constructs ListExecutionPhases
     *
     * @phpstan-param array<string, mixed> $filters
     * @phpstan-param array<string, mixed> $orderings
     */
    public function __construct(
        string $projectId,
        string $sessionId,
        ?int $page = null,
        ?int $perPage = null,
        array $filters = [],
        array $orderings = []
    ) {
        $this->execution = new GetExecution($projectId, $sessionId);
        $this->pagination = Pagination::strict($page, $perPage, $orderings);
        if ($this->pagination->perPage() > 100 || $this->pagination->offset() > 1000000) {
            throw new DomainException('Phase page bounds exceeded.');
        }

        if (array_diff(array_keys($orderings), ['latest_recorded_at']) !== []) {
            throw new DomainException('Unsupported phase ordering.');
        }

        $safe = [];
        foreach ($filters as $field => $value) {
            if (!in_array($field, ['phase_id', 'agent_id', 'name'], true)) {
                throw new DomainException('Unsupported phase filter.');
            }

            $safe[$field] = ObservationFields::reference($value);
        }

        ksort($safe, SORT_STRING);
        $this->filters = $safe;
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        ObservationFields::structure($data, ['project_id', 'session_id', 'page', 'per_page', 'filters', 'orderings']);
        if (
            !is_int($data['page']) || !is_int($data['per_page'])
            || !is_array($data['filters']) || !is_array($data['orderings'])
        ) {
            throw new DomainException('Invalid phase page.');
        }

        return new self(
            ObservationFields::reference($data['project_id']),
            ObservationFields::reference($data['session_id']),
            $data['page'],
            $data['per_page'],
            $data['filters'],
            $data['orderings']
        );
    }

    /**
     * Returns the selected execution without authority
     */
    public function execution(): GetExecution
    {
        return $this->execution;
    }

    /**
     * Returns strict finite pagination
     */
    public function pagination(): Pagination
    {
        return $this->pagination;
    }

    /**
     * Returns supported exact phase filters
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->execution->toArray() + [
            'page'      => $this->pagination->page(),
            'per_page'  => $this->pagination->perPage(),
            'filters'   => $this->filters,
            'orderings' => $this->pagination->orderings()
        ];
    }
}
