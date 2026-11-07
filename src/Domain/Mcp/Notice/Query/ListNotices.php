<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Notice\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Repository\Pagination;

/**
 * Class ListNotices
 */
final readonly class ListNotices implements Query
{
    private Pagination $pagination;

    /**
     * Constructs ListNotices
     *
     * @phpstan-param array<string, mixed> $orderings
     */
    public function __construct(
        private NoticeSelection $selection,
        ?int $page = null,
        ?int $perPage = null,
        array $orderings = []
    ) {
        $this->pagination = Pagination::strict($page, $perPage ?? 32, $orderings);
        if ($this->pagination->perPage() > 32 || $this->pagination->offset() > 1000000) {
            throw new DomainException('Notice page bounds exceeded.');
        }

        if (array_diff(array_keys($orderings), ['recorded_at']) !== []) {
            throw new DomainException('Unsupported notice ordering.');
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        NoticeSelection::structure($data, ['selection', 'page', 'per_page', 'orderings']);
        if (
            !is_array($data['selection']) || !is_int($data['page'])
            || !is_int($data['per_page']) || !is_array($data['orderings'])
        ) {
            throw new DomainException('Invalid notice page.');
        }

        return new self(
            NoticeSelection::fromArray($data['selection']),
            $data['page'],
            $data['per_page'],
            $data['orderings']
        );
    }

    /**
     * Returns the selection without authority
     */
    public function selection(): NoticeSelection
    {
        return $this->selection;
    }

    /**
     * Returns strict bounded pagination with allowlisted ordering
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
