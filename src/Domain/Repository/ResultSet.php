<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Repository;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Collection\Contract\Collection;
use Fight\Common\Domain\Collection\Traits\ItemTypeMethods;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Arrayable;
use JsonSerializable;
use Traversable;

/**
 * Class ResultSet
 *
 * @template TRecord
 * @implements Collection<int, TRecord>
 */
final class ResultSet implements Arrayable, Collection, JsonSerializable
{
    use ItemTypeMethods;

    private int $totalPages;

    /**
     * Constructs ResultSet
     *
     * Validation applies only at construction; the supplied mutable list is shared.
     * Page position and request/result agreement remain consumer responsibilities.
     *
     * @phpstan-param ArrayList<TRecord> $records
     *
     * @throws DomainException When bounds, total or record count are invalid
     */
    public function __construct(
        private int $page,
        private int $perPage,
        private int $totalRecords,
        private ArrayList $records
    ) {
        if ($page < 1 || $perPage < 1 || $totalRecords < 0) {
            throw new DomainException('Result set bounds must be positive and total nonnegative.');
        }

        $count = $records->count();
        if ($count > $perPage || $count > $totalRecords) {
            throw new DomainException('Result set record count exceeds page size or total.');
        }

        $this->setItemType($records->itemType());
        $this->totalPages = 1;
        if ($totalRecords > 0) {
            $this->totalPages = intdiv($totalRecords - 1, $perPage) + 1;
        }
    }

    /**
     * Creates a result set with validated local bounds and exact page arithmetic
     *
     * Validation applies only at construction; the supplied mutable list is shared.
     * Page position and request/result agreement remain consumer responsibilities.
     *
     * @template T
     * @phpstan-param ArrayList<T> $records
     * @phpstan-return self<T>
     *
     * @throws DomainException When bounds, total or record count are invalid
     */
    public static function strict(int $page, int $perPage, int $totalRecords, ArrayList $records): self
    {
        return new self($page, $perPage, $totalRecords, $records);
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
     *
     * @return ArrayList<TRecord>
     */
    public function records(): ArrayList
    {
        return $this->records;
    }

    /**
     * Retrieves an iterator for records
     *
     * @return Traversable<int, TRecord>
     */
    public function getIterator(): Traversable
    {
        return $this->records->getIterator();
    }

    /**
     * Retrieves pagination and records as an array
     *
     * @return array{
     *     page: int,
     *     per_page: int,
     *     total_pages: int,
     *     total_records: int,
     *     records: array<TRecord>
     * }
     */
    public function toArray(): array
    {
        return [
            'page'          => $this->page,
            'per_page'      => $this->perPage,
            'total_pages'   => $this->totalPages,
            'total_records' => $this->totalRecords,
            'records'       => $this->records->toArray()
        ];
    }

    /**
     * Retrieves a representation for JSON encoding
     *
     * @return array{
     *     page: int,
     *     per_page: int,
     *     total_pages: int,
     *     total_records: int,
     *     records: array<TRecord>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
