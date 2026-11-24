<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Repository;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use TypeError;

#[CoversClass(Pagination::class)]
class PaginationTest extends UnitTestCase
{
    public function test_that_both_paths_use_defaults_when_bounds_are_omitted(): void
    {
        foreach ([new Pagination(), Pagination::strict()] as $pagination) {
            self::assertSame(1, $pagination->page());
            self::assertSame(100, $pagination->perPage());
            self::assertSame(0, $pagination->offset());
            self::assertSame(100, $pagination->limit());
            self::assertSame([], $pagination->orderings());
        }
    }

    #[DataProvider('nullBounds')]
    public function test_that_both_paths_default_only_null_bounds(
        ?int $page,
        ?int $perPage,
        int $expectedPage,
        int $expectedPerPage,
        int $expectedOffset
    ): void {
        foreach ([new Pagination($page, $perPage), Pagination::strict($page, $perPage)] as $pagination) {
            self::assertSame($expectedPage, $pagination->page());
            self::assertSame($expectedPerPage, $pagination->perPage());
            self::assertSame($expectedOffset, $pagination->offset());
            self::assertSame($expectedPerPage, $pagination->limit());
        }
    }

    public static function nullBounds(): iterable
    {
        yield 'both null' => [null, null, 1, 100, 0];
        yield 'null page' => [null, 25, 1, 25, 0];
        yield 'null size' => [3, null, 3, 100, 200];
    }

    public function test_that_strict_construction_preserves_fields_and_normalizes_directions(): void
    {
        $orderings = ['createdAt' => 'dEsC', 'consumer.field' => 'aSc'];
        $pagination = Pagination::strict(page: 3, perPage: 25, orderings: $orderings);
        $orderings['createdAt'] = 'ASC';
        $returned = $pagination->orderings();
        $returned['consumer.field'] = 'DESC';

        self::assertSame(3, $pagination->page());
        self::assertSame(25, $pagination->perPage());
        self::assertSame(50, $pagination->offset());
        self::assertSame(25, $pagination->limit());
        self::assertSame(['createdAt' => 'DESC', 'consumer.field' => 'ASC'], $pagination->orderings());
    }

    #[DataProvider('invalidBounds')]
    public function test_that_strict_construction_rejects_nonpositive_bounds(int $page, int $perPage): void
    {
        $this->expectException(DomainException::class);

        Pagination::strict($page, $perPage);
    }

    public static function invalidBounds(): iterable
    {
        yield 'zero page' => [0, 10];
        yield 'negative page' => [-1, 10];
        yield 'minimum page' => [PHP_INT_MIN, 10];
        yield 'zero size' => [1, 0];
        yield 'negative size' => [1, -10];
        yield 'minimum size' => [1, PHP_INT_MIN];
        yield 'both negative' => [-1, -10];
    }

    #[DataProvider('invalidDirections')]
    public function test_that_strict_construction_rejects_unsupported_direction_values(mixed $direction): void
    {
        $this->expectException(DomainException::class);

        Pagination::strict(orderings: ['valid' => 'ASC', 'invalid' => $direction]);
    }

    public static function invalidDirections(): iterable
    {
        yield 'unknown' => ['invalid'];
        yield 'empty' => [''];
        yield 'leading space' => [' ASC'];
        yield 'trailing space' => ['DESC '];
        yield 'integer' => [1];
        yield 'boolean' => [true];
        yield 'null' => [null];
        yield 'array' => [[]];
        yield 'object' => [new stdClass()];
    }

    #[DataProvider('integerBoundaries')]
    public function test_that_both_paths_accept_representable_integer_boundaries(
        int $page,
        int $perPage,
        int $offset
    ): void {
        foreach ([new Pagination($page, $perPage), Pagination::strict($page, $perPage)] as $pagination) {
            self::assertSame($offset, $pagination->offset());
            self::assertSame($perPage, $pagination->limit());
            self::assertSame($page, $pagination->page());
        }
    }

    public static function integerBoundaries(): iterable
    {
        yield 'first page maximum size' => [1, PHP_INT_MAX, 0];
        yield 'exact maximum offset' => [2, PHP_INT_MAX, PHP_INT_MAX];
        yield 'maximum page unit size' => [PHP_INT_MAX, 1, PHP_INT_MAX - 1];
        yield 'last two-item page' => [intdiv(PHP_INT_MAX, 2) + 1, 2, PHP_INT_MAX - 1];
    }

    #[DataProvider('overflowingBounds')]
    public function test_that_strict_construction_rejects_offset_overflow(int $page, int $perPage): void
    {
        $this->expectException(DomainException::class);

        Pagination::strict($page, $perPage);
    }

    public static function overflowingBounds(): iterable
    {
        yield 'after exact maximum offset' => [3, PHP_INT_MAX];
        yield 'first overflowing two-item page' => [intdiv(PHP_INT_MAX, 2) + 2, 2];
        yield 'maximum bounds' => [PHP_INT_MAX, PHP_INT_MAX];
    }

    #[DataProvider('wrongParameterTypes')]
    public function test_that_strict_construction_retains_native_strict_caller_type_errors(
        mixed $page,
        mixed $perPage,
        mixed $orderings
    ): void {
        $this->expectException(TypeError::class);

        Pagination::strict($page, $perPage, $orderings);
    }

    public static function wrongParameterTypes(): iterable
    {
        yield 'string page' => ['2', 10, []];
        yield 'float size' => [1, 10.5, []];
        yield 'boolean page' => [true, 10, []];
        yield 'non-array orderings' => [1, 10, 'ASC'];
    }

    #[DataProvider('invalidBounds')]
    #[DataProvider('overflowingBounds')]
    public function test_that_constructor_rejects_invalid_bounds(int $page, int $perPage): void
    {
        $this->expectException(DomainException::class);

        new Pagination(page: $page, perPage: $perPage);
    }

    #[DataProvider('invalidDirections')]
    public function test_that_constructor_rejects_unsupported_direction_values(mixed $direction): void
    {
        $this->expectException(DomainException::class);

        new Pagination(orderings: ['valid' => 'ASC', 'invalid' => $direction]);
    }

    #[DataProvider('wrongParameterTypes')]
    public function test_that_constructor_retains_native_strict_caller_type_errors(
        mixed $page,
        mixed $perPage,
        mixed $orderings
    ): void {
        $this->expectException(TypeError::class);

        new Pagination($page, $perPage, $orderings);
    }

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
        $pagination = new Pagination(page: 1, perPage: 10, orderings: ['name' => 'aSc', 'date' => 'dEsC']);

        self::assertSame(['name' => 'ASC', 'date' => 'DESC'], $pagination->orderings());
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
}
