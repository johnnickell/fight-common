<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Repository;

/**
 * Class Pagination
 */
final readonly class Pagination
{
    public const string ASC = 'ASC';

    public const string DESC = 'DESC';

    public const int DEFAULT_PAGE = 1;

    public const int DEFAULT_PER_PAGE = 100;

    private int $page;

    private int $perPage;

    private int $offset;

    private int $limit;

    /** @var array<string, string> */
    private array $orderings;

    /**
     * Constructs Pagination
     *
     * @param integer|null $page
     * @param integer|null $perPage
     * @param array<string, string> $orderings
     */
    public function __construct(?int $page = null, ?int $perPage = null, array $orderings = [])
    {
        $this->page = $page ?: static::DEFAULT_PAGE;
        $this->perPage = $perPage ?: static::DEFAULT_PER_PAGE;
        $this->offset = ($this->page - 1) * $this->perPage;
        $this->limit = $this->perPage;
        $this->orderings = array_map(function (string $ordering) {
            if (strtoupper($ordering) === static::DESC) {
                return static::DESC;
            }

            return static::ASC;
        }, $orderings);
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
     * Retrieves the offset
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Retrieves the limit
     */
    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * Retrieves the orderings
     *
     * @return array<string, string>
     */
    public function orderings(): array
    {
        return $this->orderings;
    }
}
