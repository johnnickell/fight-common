<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Subscription\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Subscription\SubscriberSelection;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Repository\Pagination;

/**
 * Class ListSubscribers
 */
final readonly class ListSubscribers implements Query
{
    private Pagination $pagination;

    /**
     * Constructs ListSubscribers
     *
     * @phpstan-param array<string, mixed> $orderings
     */
    public function __construct(
        private SubscriberSelection $selection,
        ?int $page = null,
        ?int $perPage = null,
        array $orderings = []
    ) {
        $this->pagination = Pagination::strict($page, $perPage ?? 16, $orderings);
        if (
            $this->pagination->perPage() > 16 || $this->pagination->offset() > 1000000
            || array_diff(array_keys($orderings), ['opened_at']) !== []
        ) {
            throw new DomainException('Invalid subscriber page bounds or ordering.');
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        NoticeSelection::structure($data, ['selection', 'page', 'per_page', 'orderings']);
        if (
            !is_array($data['selection']) || !is_int($data['page']) || !is_int($data['per_page'])
            || !is_array($data['orderings'])
        ) {
            throw new DomainException('Invalid subscriber page.');
        }

        return new self(
            SubscriberSelection::fromArray($data['selection']),
            $data['page'],
            $data['per_page'],
            $data['orderings']
        );
    }

    /**
     * Returns the selection without authority
     */
    public function selection(): SubscriberSelection
    {
        return $this->selection;
    }

    /**
     * Returns strict bounded pagination
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
