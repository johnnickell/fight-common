<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Repository;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ResultSet::class)]
class ResultSetTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function test_that_page_returns_correct_value(): void
    {
        $resultSet = new ResultSet(3, 25, 100, ArrayList::of('string'));

        self::assertSame(3, $resultSet->page());
    }

    public function test_that_per_page_returns_correct_value(): void
    {
        $resultSet = new ResultSet(1, 25, 100, ArrayList::of('string'));

        self::assertSame(25, $resultSet->perPage());
    }

    public function test_that_total_records_returns_correct_value(): void
    {
        $resultSet = new ResultSet(1, 25, 100, ArrayList::of('string'));

        self::assertSame(100, $resultSet->totalRecords());
    }

    public function test_that_records_returns_the_array_list(): void
    {
        $list = ArrayList::of('string');
        $resultSet = new ResultSet(1, 25, 0, $list);

        self::assertSame($list, $resultSet->records());
    }

    // -------------------------------------------------------------------------
    // totalPages calculation
    // -------------------------------------------------------------------------

    public function test_that_total_pages_is_correct_for_exact_multiple(): void
    {
        $resultSet = new ResultSet(1, 25, 100, ArrayList::of('string'));

        self::assertSame(4, $resultSet->totalPages());
    }

    public function test_that_total_pages_rounds_up_for_remainder(): void
    {
        $resultSet = new ResultSet(1, 25, 101, ArrayList::of('string'));

        self::assertSame(5, $resultSet->totalPages());
    }

    public function test_that_total_pages_is_one_when_records_fit_on_a_single_page(): void
    {
        $resultSet = new ResultSet(1, 25, 10, ArrayList::of('string'));

        self::assertSame(1, $resultSet->totalPages());
    }

    public function test_that_total_pages_is_one_when_total_records_is_zero(): void
    {
        $resultSet = new ResultSet(1, 25, 0, ArrayList::of('string'));

        self::assertSame(1, $resultSet->totalPages());
    }

    public function test_that_total_pages_is_one_when_per_page_is_zero(): void
    {
        $resultSet = new ResultSet(1, 0, 100, ArrayList::of('string'));

        self::assertSame(1, $resultSet->totalPages());
    }

    // -------------------------------------------------------------------------
    // isEmpty / count
    // -------------------------------------------------------------------------

    public function test_that_is_empty_returns_true_when_records_list_is_empty(): void
    {
        $resultSet = new ResultSet(1, 25, 0, ArrayList::of('string'));

        self::assertTrue($resultSet->isEmpty());
    }

    public function test_that_is_empty_returns_false_when_records_exist(): void
    {
        $list = ArrayList::of('string');
        $list->add('item');
        $resultSet = new ResultSet(1, 25, 1, $list);

        self::assertFalse($resultSet->isEmpty());
    }

    public function test_that_count_returns_the_record_count(): void
    {
        $list = ArrayList::of('string');
        $list->add('a');
        $list->add('b');
        $resultSet = new ResultSet(1, 25, 2, $list);

        self::assertSame(2, $resultSet->count());
    }

    // -------------------------------------------------------------------------
    // toArray / jsonSerialize
    // -------------------------------------------------------------------------

    public function test_that_to_array_returns_expected_structure(): void
    {
        $list = ArrayList::of('string');
        $list->add('foo');
        $resultSet = new ResultSet(2, 10, 15, $list);

        self::assertSame([
            'page'          => 2,
            'per_page'      => 10,
            'total_pages'   => 2,
            'total_records' => 15,
            'records'       => ['foo'],
        ], $resultSet->toArray());
    }

    public function test_that_json_serialize_matches_to_array(): void
    {
        $list = ArrayList::of('string');
        $list->add('bar');
        $resultSet = new ResultSet(1, 5, 1, $list);

        self::assertSame($resultSet->toArray(), $resultSet->jsonSerialize());
    }

    // -------------------------------------------------------------------------
    // Iteration
    // -------------------------------------------------------------------------

    public function test_that_iteration_visits_all_records(): void
    {
        $list = ArrayList::of('string');
        $list->add('x');
        $list->add('y');
        $list->add('z');
        $resultSet = new ResultSet(1, 25, 3, $list);

        $visited = [];
        foreach ($resultSet as $item) {
            $visited[] = $item;
        }

        self::assertSame(['x', 'y', 'z'], $visited);
    }
}
