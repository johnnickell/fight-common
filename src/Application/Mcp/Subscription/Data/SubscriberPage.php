<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription\Data;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Subscription\Query\ListSubscribers;
use Fight\Common\Domain\Mcp\Subscription\SubscriberSelection;

/**
 * Class SubscriberPage
 */
final readonly class SubscriberPage
{
    /**
     * @var list<SubscriberView>
     */
    private array $subscribers;

    /**
     * Constructs SubscriberPage
     *
     * @param array<array-key, SubscriberView> $subscribers
     */
    public function __construct(
        array $subscribers,
        private int $total,
        private int $page,
        private int $perPage,
        private SubscriberReadScope $scope
    ) {
        new ListSubscribers(SubscriberSelection::create([]), $page, $perPage);
        if (
            !array_is_list($subscribers) || count($subscribers) > $perPage
            || $total < count($subscribers) || $total > 8192
        ) {
            throw new DomainException('Invalid subscriber page result.');
        }

        $this->subscribers = array_map(static fn (SubscriberView $view): SubscriberView => $view, $subscribers);
        if (strlen(json_encode($this->toArray(), JSON_THROW_ON_ERROR)) > 262144) {
            throw new DomainException('Subscriber page exceeds encoded result bound.');
        }
    }

    /**
     * Returns bounded permitted complete observations
     *
     * @return list<SubscriberView>
     */
    public function subscribers(): array
    {
        return $this->subscribers;
    }

    /**
     * Returns full-selection total and live page metadata without a retention or access lease
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scope'       => $this->scope->toArray(),
            'subscribers' => array_map(static fn (SubscriberView $view): array => $view->toArray(), $this->subscribers),
            'total'       => $this->total,
            'page'        => $this->page,
            'per_page'    => $this->perPage
        ];
    }
}
