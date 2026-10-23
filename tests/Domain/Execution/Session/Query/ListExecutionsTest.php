<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Execution\Session\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Execution\Session\Query\ListExecutions;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ListExecutions::class)]
final class ListExecutionsTest extends UnitTestCase
{
    public function test_that_query_roundtrips_selection_and_normalized_pagination_without_authority(): void
    {
        $selection = ExecutionSelection::create(['project'], ['session_id' => 'session']);
        $query = new ListExecutions($selection, 2, 20, ['latest_recorded_at' => 'asc']);
        self::assertSame($selection, $query->selection());
        self::assertSame(20, $query->pagination()->offset());
        self::assertSame([
            'selection' => ['projects' => ['project'], 'filters' => ['session_id' => 'session']],
            'page' => 2,
            'per_page' => 20,
            'orderings' => ['latest_recorded_at' => 'ASC']
        ], $query->toArray());
        self::assertSame($query->toArray(), ListExecutions::fromArray($query->toArray())->toArray());
    }

    #[DataProvider('invalidPages')]
    public function test_that_pages_reject_invalid_excessive_overflowing_or_unsafe_ordering(?int $page, ?int $perPage, array $orderings): void
    {
        $this->expectException(DomainException::class);
        new ListExecutions(ExecutionSelection::create(['project']), $page, $perPage, $orderings);
    }

    public static function invalidPages(): iterable
    {
        yield [0, 1, []];
        yield [1, 0, []];
        yield [1, 101, []];
        yield [1000002, 1, []];
        yield [PHP_INT_MAX, PHP_INT_MAX, []];
        yield [1, 1, ['payload; DROP TABLE x' => 'ASC']];
        yield [1, 1, ['latest_recorded_at' => 'sideways']];
    }

    public function test_that_query_rejects_noncanonical_payload_shapes(): void
    {
        $this->expectException(DomainException::class);
        ListExecutions::fromArray(['selection' => [], 'page' => '1', 'per_page' => 10, 'orderings' => []]);
    }
}
