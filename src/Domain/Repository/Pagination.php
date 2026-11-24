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
     * Null bounds select the defaults. Ordering fields remain consumer-owned.
     *
     * @phpstan-param array<string, mixed> $orderings
     *
     * @throws DomainException When bounds, directions or the integer offset are invalid
     */
    public function __construct(?int $page = null, ?int $perPage = null, array $orderings = [])
    {
        $page ??= self::DEFAULT_PAGE;
        $perPage ??= self::DEFAULT_PER_PAGE;
        if ($page < 1 || $perPage < 1) {
            throw new DomainException('Pagination bounds must be positive.');
        }

        if ($page - 1 > intdiv(PHP_INT_MAX, $perPage)) {
            throw new DomainException('Pagination offset exceeds the integer range.');
        }

        $normalizedOrderings = [];
        foreach ($orderings as $field => $ordering) {
            $direction = is_string($ordering) ? strtoupper($ordering) : null;
            if (!in_array($direction, [self::ASC, self::DESC], true)) {
                throw new DomainException('Pagination directions must be ASC or DESC.');
            }

            $normalizedOrderings[$field] = $direction;
        }

        $this->page = $page;
        $this->perPage = $perPage;
        $this->offset = ($page - 1) * $perPage;
        $this->limit = $perPage;
        $this->orderings = $normalizedOrderings;
    }

    /**
     * Creates pagination with positive bounds and supported ordering directions
     *
     * Null bounds select the existing defaults. PHP owns parameter type enforcement;
     * both construction paths validate resolved integers and direction values without coercion.
     * Ordering fields remain consumer-owned, not validated SQL identifiers.
     *
     * @phpstan-param array<string, mixed> $orderings
     *
     * @throws DomainException When bounds, directions or the integer offset are invalid
     */
    public static function strict(?int $page = null, ?int $perPage = null, array $orderings = []): self
    {
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
