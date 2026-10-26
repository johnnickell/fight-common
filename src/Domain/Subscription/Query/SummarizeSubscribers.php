<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Subscription\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Subscription\SubscriberSelection;

/**
 * Class SummarizeSubscribers
 */
final readonly class SummarizeSubscribers implements Query
{
    /**
     * Constructs SummarizeSubscribers
     */
    public function __construct(private SubscriberSelection $selection)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        NoticeSelection::structure($data, ['selection']);
        if (!is_array($data['selection'])) {
            throw new DomainException('Invalid subscriber summary.');
        }

        return new self(SubscriberSelection::fromArray($data['selection']));
    }

    /**
     * Returns the selection without authority
     */
    public function selection(): SubscriberSelection
    {
        return $this->selection;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['selection' => $this->selection->toArray()];
    }
}
