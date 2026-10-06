<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Repository;

use Fight\Common\Domain\Exception\DomainException;

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
    /**
     * @var array<string, string>
     */
    private array $orderings;

    /**
     * Constructs Pagination
     *
     * @param integer|null $page
     * @param integer|null $perPage
     * @param array<string, string> $orderings
     *
     * @deprecated Use strict() for validated bounds and ordering; retained for minor-release compatibility.
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
     * Creates pagination with positive bounds and supported ordering directions
     *
     * Null bounds select the existing defaults. PHP owns parameter type enforcement;
     * this factory validates resolved integers and direction values without coercion.
     * Ordering fields remain consumer-owned, not validated SQL identifiers.
     *
     * @phpstan-param array<string, mixed> $orderings
     *
     * @throws DomainException When bounds, directions or the integer offset are invalid
     */
    public static function strict(?int $page = null, ?int $perPage = null, array $orderings = []): self
    {
        $page ??= self::DEFAULT_PAGE;
        $perPage ??= self::DEFAULT_PER_PAGE;
        if ($page < 1 || $perPage < 1) {
            throw new DomainException('Pagination bounds must be positive.');
        }

        if ($page - 1 > intdiv(PHP_INT_MAX, $perPage)) {
            throw new DomainException('Pagination offset exceeds the integer range.');
        }

        foreach ($orderings as $ordering) {
            if (!is_string($ordering) || !in_array(strtoupper($ordering), [self::ASC, self::DESC], true)) {
                throw new DomainException('Pagination directions must be ASC or DESC.');
            }
        }

        return new self($page, $perPage, $orderings);
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
