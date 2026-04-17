<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Repository;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Collection\Contract\Collection;
use Fight\Common\Domain\Collection\Traits\ItemTypeMethods;
use JsonSerializable;
use Traversable;

/**
 * Class ResultSet
 */
final class ResultSet implements Arrayable, Collection, JsonSerializable
{
    use ItemTypeMethods;

    private int $totalPages;

    /**
     * Constructs ResultSet
     */
    public function __construct(
        private int $page,
        private int $perPage,
        private int $totalRecords,
        private ArrayList $records
    ) {
        $this->setItemType($this->records->itemType());
        $this->totalPages = $this->countPages(
            $this->totalRecords,
            $this->perPage
        );
    }

    /**
     * @inheritDoc
     */
    public function isEmpty(): bool
    {
        return $this->records->isEmpty();
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->records->count();
    }

    /**
     * Retrieves the page number
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Retrieves the number of items per page
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Retrieves the number of total pages
     */
    public function totalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * Retrieves the number of total records
     */
    public function totalRecords(): int
    {
        return $this->totalRecords;
    }

    /**
     * Retrieves the records
     */
    public function records(): ArrayList
    {
        return $this->records;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return $this->records->getIterator();
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'page'          => $this->page,
            'per_page'      => $this->perPage,
            'total_pages'   => $this->totalPages,
            'total_records' => $this->totalRecords,
            'records'       => $this->records
        ];
    }

    /**
     * Retrieves a representation for JSON encoding
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Calculates the number of pages
     */
    private function countPages(int $totalRecords, int $perPage): int
    {
        if ($totalRecords < 1 || $perPage < 1) {
            return 1;
        }

        return intval((($totalRecords - 1) / $perPage) + 1);
    }
}
