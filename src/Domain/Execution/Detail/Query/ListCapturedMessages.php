<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Detail\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Detail\DetailSelection;
use Fight\Common\Domain\Execution\Value\ObservationFields;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Repository\Pagination;

/**
 * Class ListCapturedMessages
 */
final readonly class ListCapturedMessages implements Query
{
    private Pagination $pagination;

    /**
     * Constructs ListCapturedMessages
     *
     * @phpstan-param array<string, mixed> $orderings
     */
    public function __construct(
        private DetailSelection $selection,
        ?int $page = null,
        ?int $perPage = 32,
        array $orderings = []
    ) {
        $this->pagination = Pagination::strict($page, $perPage ?? 32, $orderings);
        if ($this->pagination->perPage() > 32 || $this->pagination->offset() > 1000000) {
            throw new DomainException('Captured message page bounds exceeded.');
        }

        if (array_diff(array_keys($orderings), ['recorded_at']) !== []) {
            throw new DomainException('Unsupported captured message ordering.');
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
            throw new DomainException('Invalid captured message page.');
        }

        return new self(
            DetailSelection::fromArray($data['selection']),
            $data['page'],
            $data['per_page'],
            $data['orderings']
        );
    }

    /**
     * Returns the independently supplied selection
     */
    public function selection(): DetailSelection
    {
        return $this->selection;
    }

    /**
     * Returns strict bounded live pagination
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
