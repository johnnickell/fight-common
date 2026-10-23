<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Observation\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Session\Query\GetExecution;
use Fight\Common\Domain\Execution\Value\ObservationFields;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Repository\Pagination;

/**
 * Class ListPhaseActivity
 */
final readonly class ListPhaseActivity implements Query
{
    private GetExecution $execution;
    private string $phaseId;
    private Pagination $pagination;
    /**
     * @var array<string, string>
     */
    private array $filters;

    /**
     * Constructs ListPhaseActivity
     *
     * @phpstan-param array<string, mixed> $filters
     * @phpstan-param array<string, mixed> $orderings
     */
    public function __construct(
        string $projectId,
        string $sessionId,
        string $phaseId,
        ?int $page = null,
        ?int $perPage = null,
        array $filters = [],
        array $orderings = []
    ) {
        $this->execution = new GetExecution($projectId, $sessionId);
        $this->phaseId = ObservationFields::reference($phaseId);
        $this->pagination = Pagination::strict($page, $perPage, $orderings);
        if ($this->pagination->perPage() > 100 || $this->pagination->offset() > 1000000) {
            throw new DomainException('Activity page bounds exceeded.');
        }

        if (array_diff(array_keys($orderings), ['recorded_at']) !== []) {
            throw new DomainException('Unsupported activity ordering.');
        }

        $safe = [];
        foreach ($filters as $field => $value) {
            if (!in_array($field, ['producer_id', 'kind', 'model_id'], true)) {
                throw new DomainException('Unsupported activity filter.');
            }

            $safe[$field] = $field === 'kind' ? ObservationFields::name($value) : ObservationFields::reference($value);
        }

        ksort($safe, SORT_STRING);
        $this->filters = $safe;
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        ObservationFields::structure($data, [
            'project_id', 'session_id', 'phase_id', 'page', 'per_page', 'filters', 'orderings'
        ]);
        if (
            !is_int($data['page']) || !is_int($data['per_page'])
            || !is_array($data['filters']) || !is_array($data['orderings'])
        ) {
            throw new DomainException('Invalid activity page.');
        }

        return new self(
            ObservationFields::reference($data['project_id']),
            ObservationFields::reference($data['session_id']),
            ObservationFields::reference($data['phase_id']),
            $data['page'],
            $data['per_page'],
            $data['filters'],
            $data['orderings']
        );
    }

    /**
     * Returns the exact selected execution without authority
     */
    public function execution(): GetExecution
    {
        return $this->execution;
    }

    /**
     * Returns the selected phase reference
     */
    public function phaseId(): string
    {
        return $this->phaseId;
    }

    /**
     * Returns strict finite page bounds
     */
    public function pagination(): Pagination
    {
        return $this->pagination;
    }

    /**
     * Returns supported exact activity filters
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
            'phase_id'  => $this->phaseId,
            'page'      => $this->pagination->page(),
            'per_page'  => $this->pagination->perPage(),
            'filters'   => $this->filters,
            'orderings' => $this->pagination->orderings()
        ];
    }
}
