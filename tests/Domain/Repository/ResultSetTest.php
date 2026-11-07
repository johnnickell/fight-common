<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Repository;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use TypeError;

#[CoversClass(ResultSet::class)]
class ResultSetTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{int, int, int, list<string>}>
     */
    public static function invalidStrictPages(): iterable
    {
        foreach ([0, -1, PHP_INT_MIN] as $bound) {
            yield 'page '.$bound => [$bound, 2, 2, []];
            yield 'size '.$bound => [1, $bound, 2, []];
        }
        yield 'negative total' => [1, 2, -1, []];
        yield 'minimum total' => [1, 2, PHP_INT_MIN, []];
        yield 'count exceeds size only' => [1, 1, 3, ['a', 'b']];
        yield 'count exceeds total only' => [1, 3, 1, ['a', 'b']];
        yield 'nonempty zero total' => [1, 3, 0, ['a']];
    }

    #[DataProvider('invalidStrictPages')]
    public function test_that_strict_rejects_invalid_local_bounds_and_counts(
        int $page,
        int $perPage,
        int $totalRecords,
        array $items
    ): void {
        $records = ArrayList::of('string');
        foreach ($items as $item) {
            $records->add($item);
        }

        $this->expectException(DomainException::class);
        ResultSet::strict($page, $perPage, $totalRecords, $records);
    }

    /**
     * @return iterable<string, array{mixed, mixed, mixed, mixed}>
     */
    public static function invalidNativeArguments(): iterable
    {
        yield 'string page' => ['1', 1, 1, ArrayList::of()];
        yield 'float size' => [1, 1.0, 1, ArrayList::of()];
        yield 'boolean total' => [1, 1, true, ArrayList::of()];
        yield 'null page' => [null, 1, 1, ArrayList::of()];
        yield 'null size' => [1, null, 1, ArrayList::of()];
        yield 'null total' => [1, 1, null, ArrayList::of()];
        yield 'array records' => [1, 1, 1, []];
        yield 'object records' => [1, 1, 1, new stdClass()];
        yield 'null records' => [1, 1, 1, null];
    }

    #[DataProvider('invalidNativeArguments')]
    public function test_that_strict_retains_native_type_errors(
        mixed $page,
        mixed $perPage,
        mixed $totalRecords,
        mixed $records
    ): void {
        $this->expectException(TypeError::class);
        ResultSet::strict($page, $perPage, $totalRecords, $records);
    }

    /**
     * @return iterable<string, array{int, int, int, int, int}>
     */
    public static function legitimateStrictPages(): iterable
    {
        yield 'zero total' => [1, 2, 0, 0, 1];
        yield 'zero total beyond range' => [9, 2, 0, 0, 1];
        yield 'positive total empty first' => [1, 2, 5, 0, 3];
        yield 'positive total empty middle' => [2, 2, 5, 0, 3];
        yield 'positive total empty beyond range' => [8, 2, 5, 0, 3];
        yield 'partial first' => [1, 2, 5, 1, 3];
        yield 'partial middle' => [2, 2, 5, 1, 3];
        yield 'ordinary full' => [2, 2, 5, 2, 3];
        yield 'last remainder' => [3, 2, 5, 1, 3];
        yield 'last not limited to remainder' => [3, 2, 5, 2, 3];
        yield 'nonempty beyond range' => [9, 2, 5, 2, 3];
        yield 'count equals both bounds' => [1, 2, 2, 2, 1];
        yield 'count equals total only' => [1, 3, 2, 2, 1];
        yield 'one' => [1, 1, 1, 1, 1];
        yield 'exact multiple' => [1, 2, 6, 2, 3];
        yield 'maximum total size one' => [1, 1, PHP_INT_MAX, 1, PHP_INT_MAX];
        yield 'maximum total size two' => [1, 2, PHP_INT_MAX, 2, intdiv(PHP_INT_MAX, 2) + 1];
        yield 'near maximum exact multiple' => [1, 2, PHP_INT_MAX - 1, 2, intdiv(PHP_INT_MAX - 1, 2)];
        yield 'near maximum remainder' => [1, PHP_INT_MAX - 1, PHP_INT_MAX, 1, 2];
        yield 'maximum size and total' => [1, PHP_INT_MAX, PHP_INT_MAX, 1, 1];
        yield 'maximum page no offset constraint' => [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, 1, 1];
        yield 'maximum page small total' => [PHP_INT_MAX, PHP_INT_MAX, 1, 1, 1];
        yield 'maximum page empty total' => [PHP_INT_MAX, PHP_INT_MAX, 0, 0, 1];
    }

    #[DataProvider('legitimateStrictPages')]
    public function test_that_strict_accepts_local_pages_with_exact_integer_metadata(
        int $page,
        int $perPage,
        int $totalRecords,
        int $count,
        int $totalPages
    ): void {
        $records = ArrayList::of('int');
        for ($item = 0; $item < $count; ++$item) {
            $records->add($item);
        }

        $result = ResultSet::strict(
            page: $page,
            perPage: $perPage,
            totalRecords: $totalRecords,
            records: $records
        );

        self::assertSame($page, $result->page());
        self::assertSame($perPage, $result->perPage());
        self::assertSame($totalRecords, $result->totalRecords());
        self::assertSame($totalPages, $result->totalPages());
        self::assertSame($count, $result->count());
        self::assertSame($count === 0, $result->isEmpty());
        self::assertSame($records, $result->records());
        self::assertSame($records->toArray(), iterator_to_array($result));
        $expected = [
            'page'          => $page,
            'per_page'      => $perPage,
            'total_pages'   => $totalPages,
            'total_records' => $totalRecords,
            'records'       => $records->toArray()
        ];
        self::assertSame($expected, $result->toArray());
        self::assertSame($expected, $result->jsonSerialize());
        self::assertSame($expected, json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{?string, list<mixed>}>
     */
    public static function recordTypes(): iterable
    {
        yield 'scalar' => ['string', ['first', 'second']];
        yield 'class' => [stdClass::class, [new stdClass()]];
        yield 'interface' => [Arrayable::class, [new class implements Arrayable {
            public function toArray(): array
            {
                return ['id' => 7];
            }
        }]];
        yield 'object' => ['object', [new stdClass()]];
        yield 'dynamic' => [null, ['text', 42, new stdClass()]];
    }

    #[DataProvider('recordTypes')]
    public function test_that_both_paths_preserve_declared_type_and_record_identity(?string $type, array $items): void
    {
        $records = ArrayList::of($type);
        foreach ([false, true] as $populated) {
            if ($populated) {
                foreach ($items as $item) {
                    $records->add($item);
                }
            }
            foreach ([new ResultSet(1, 3, 3, $records), ResultSet::strict(1, 3, 3, $records)] as $result) {
                self::assertSame($type, $result->itemType());
                self::assertSame($records->itemType(), $result->itemType());
                self::assertSame($records, $result->records());
                self::assertSame($records->toArray(), iterator_to_array($result));
                self::assertSame($records->toArray(), $result->toArray()['records']);
                if ($populated) {
                    self::assertSame($items[0], $result->records()->get(0));
                }
            }
        }
    }

    public function test_that_both_paths_expose_caller_and_records_mutation_without_revalidation(): void
    {
        foreach ([false, true] as $strict) {
            $original = new stdClass();
            $records = ArrayList::of(stdClass::class);
            $records->add($original);
            $result = $strict ? ResultSet::strict(1, 1, 1, $records) : new ResultSet(1, 1, 1, $records);
            self::assertSame($original, $result->records()->get(0));

            $second = new stdClass();
            $records->add($second);
            self::assertSame(2, $result->count());
            $third = new stdClass();
            $result->records()->add($third);
            self::assertSame([$original, $second, $third], $records->toArray());
            self::assertSame([$original, $second, $third], iterator_to_array($result));
            self::assertSame([$original, $second, $third], $result->jsonSerialize()['records']);
            self::assertSame(1, $result->perPage());
            self::assertSame(1, $result->totalRecords());
            self::assertSame(1, $result->totalPages());
            $original->name = 'changed';
            self::assertSame('changed', $result->records()->get(0)->name);
            $records->remove(2);
            $records->remove(1);
            $records->remove(0);
            self::assertTrue($result->isEmpty());
            self::assertSame([], $result->toArray()['records']);
        }
    }

    public function test_that_legacy_constructor_remains_permissive_for_inconsistent_metadata(): void
    {
        $records = ArrayList::of('string');
        $records->add('a');
        $records->add('b');
        foreach ([[0, 0, 0], [-1, -1, -1], [1, 1, 1]] as [$page, $perPage, $totalRecords]) {
            $result = new ResultSet(page: $page, perPage: $perPage, totalRecords: $totalRecords, records: $records);
            self::assertSame($page, $result->page());
            self::assertSame($perPage, $result->perPage());
            self::assertSame($totalRecords, $result->totalRecords());
            self::assertSame(1, $result->totalPages());
            self::assertSame(2, $result->count());
            self::assertSame($records, $result->records());
        }
    }

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

    public function test_that_to_array_preserves_arbitrary_record_types_and_pagination(): void
    {
        $object = new \stdClass();
        $list = ArrayList::of();
        $list->add('scalar');
        $list->add($object);

        $resultSet = new ResultSet(2, 2, 3, $list);

        self::assertSame([
            'page'          => 2,
            'per_page'      => 2,
            'total_pages'   => 2,
            'total_records' => 3,
            'records'       => ['scalar', $object]
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
