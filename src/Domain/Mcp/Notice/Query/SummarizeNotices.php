<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Notice\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class SummarizeNotices
 */
final readonly class SummarizeNotices implements Query
{
    /**
     * Constructs SummarizeNotices
     */
    public function __construct(private NoticeSelection $selection)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        NoticeSelection::structure($data, ['selection']);
        if (!is_array($data['selection'])) {
            throw new DomainException('Invalid notice summary.');
        }

        return new self(NoticeSelection::fromArray($data['selection']));
    }

    /**
     * Returns the selection without authority
     */
    public function selection(): NoticeSelection
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
