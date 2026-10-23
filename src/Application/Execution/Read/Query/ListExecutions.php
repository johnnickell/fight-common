<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Read\Query;

use Fight\Common\Application\Execution\Read\ExecutionSelection;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Value\ObservationFields;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Repository\Pagination;

/**
 * Class ListExecutions
 */
final readonly class ListExecutions implements Query
{
    private Pagination $pagination;

    /**
     * Constructs ListExecutions
     *
     * @phpstan-param array<string, mixed> $orderings
     */
    public function __construct(
        private ExecutionSelection $selection,
        ?int $page = null,
        ?int $perPage = null,
        array $orderings = []
    ) {
        $this->pagination = Pagination::strict($page, $perPage, $orderings);
        if ($this->pagination->perPage() > 100 || $this->pagination->offset() > 1000000) {
            throw new DomainException('Execution page bounds exceeded.');
        }

        if (array_diff(array_keys($orderings), ['latest_recorded_at']) !== []) {
            throw new DomainException('Unsupported execution ordering.');
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        ObservationFields::structure($data, ['selection', 'page', 'per_page', 'orderings']);
        if (
            !is_array($data['selection']) || !is_int($data['page'])
            || !is_int($data['per_page']) || !is_array($data['orderings'])
        ) {
            throw new DomainException('Invalid execution page.');
        }

        return new self(
            ExecutionSelection::fromArray($data['selection']),
            $data['page'],
            $data['per_page'],
            $data['orderings']
        );
    }

    /**
     * Returns the independently supplied selection
     */
    public function selection(): ExecutionSelection
    {
        return $this->selection;
    }

    /**
     * Returns strict finite pagination with allowlisted ordering
     */
    public function pagination(): Pagination
    {
        return $this->pagination;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'selection' => $this->selection->toArray(),
            'page'      => $this->pagination->page(),
            'per_page'  => $this->pagination->perPage(),
            'orderings' => $this->pagination->orderings()
        ];
    }
}
