<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Repository;

use Fight\Common\Domain\Repository\Pagination;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Pagination::class)]
class PaginationTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function test_that_page_returns_the_provided_value(): void
    {
        $pagination = new Pagination(3, 25);

        self::assertSame(3, $pagination->page());
    }

    public function test_that_per_page_returns_the_provided_value(): void
    {
        $pagination = new Pagination(1, 50);

        self::assertSame(50, $pagination->perPage());
    }

    public function test_that_orderings_returns_normalized_direction_values(): void
    {
        $pagination = new Pagination(1, 10, ['name' => 'asc', 'date' => 'DESC', 'id' => 'invalid']);

        self::assertSame(['name' => 'ASC', 'date' => 'DESC', 'id' => 'ASC'], $pagination->orderings());
    }

    public function test_that_orderings_returns_empty_array_when_none_provided(): void
    {
        $pagination = new Pagination(1, 10);

        self::assertSame([], $pagination->orderings());
    }

    public function test_that_offset_is_calculated_from_page_and_per_page(): void
    {
        $pagination = new Pagination(3, 25);

        self::assertSame(50, $pagination->offset());
    }

    public function test_that_limit_equals_per_page(): void
    {
        $pagination = new Pagination(1, 30);

        self::assertSame(30, $pagination->limit());
    }

    // -------------------------------------------------------------------------
    // Defaults
    // -------------------------------------------------------------------------

    public function test_that_null_page_falls_back_to_default(): void
    {
        $pagination = new Pagination(null, 10);

        self::assertSame(Pagination::DEFAULT_PAGE, $pagination->page());
    }

    public function test_that_zero_page_falls_back_to_default(): void
    {
        $pagination = new Pagination(0, 10);

        self::assertSame(Pagination::DEFAULT_PAGE, $pagination->page());
    }

    public function test_that_null_per_page_falls_back_to_default(): void
    {
        $pagination = new Pagination(1, null);

        self::assertSame(Pagination::DEFAULT_PER_PAGE, $pagination->perPage());
    }

    public function test_that_zero_per_page_falls_back_to_default(): void
    {
        $pagination = new Pagination(1, 0);

        self::assertSame(Pagination::DEFAULT_PER_PAGE, $pagination->perPage());
    }
}
