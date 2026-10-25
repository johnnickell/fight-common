<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Notice\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Notice\Query\GetNotice;
use Fight\Common\Domain\Notice\Query\ListNotices;
use Fight\Common\Domain\Notice\Query\SummarizeNotices;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(GetNotice::class)]
#[CoversClass(ListNotices::class)]
#[CoversClass(SummarizeNotices::class)]
final class NoticeQueriesTest extends UnitTestCase
{
    public function test_that_payloads_round_trip_with_finite_defaults_and_no_grants(): void
    {
        $selection = NoticeSelection::create(['scope']);
        foreach ([new SummarizeNotices($selection), new GetNotice('scope', 'producer', 'id'), new ListNotices($selection), new ListNotices($selection, 3, 2, ['recorded_at' => 'asc'])] as $query) {
            self::assertSame($query->toArray(), $query::fromArray($query->toArray())->toArray());
            self::assertSame($query->selection()->toArray(), $query::fromArray($query->toArray())->selection()->toArray());
        }
        self::assertSame(['selection' => $selection->toArray(), 'page' => 1, 'per_page' => 32, 'orderings' => []], (new ListNotices($selection))->toArray());
        self::assertSame(4, (new ListNotices($selection, 3, 2))->pagination()->offset());
        self::assertSame(1000000, (new ListNotices($selection, 1000001, 1))->pagination()->offset());
        self::assertSame(['scope' => 'scope', 'id' => 'id', 'producer' => 'producer'], (new GetNotice('scope', 'producer', 'id'))->toArray());
    }

    #[DataProvider('invalidQueries')]
    public function test_that_queries_reject_unsafe_shapes_and_pagination(string $class, array $data): void
    {
        $this->expectException(DomainException::class);
        $class::fromArray($data);
    }

    public static function invalidQueries(): iterable
    {
        yield [SummarizeNotices::class, ['selection' => null]];
        yield [SummarizeNotices::class, ['selection' => [], 'access' => 'SUPER_ADMIN']];
        yield [GetNotice::class, ['scope' => 'scope', 'producer' => 'producer', 'id' => []]];
        $data = ['selection' => ['scopes' => ['scope'], 'filters' => []], 'page' => 1, 'per_page' => 32, 'orderings' => []];
        foreach (['selection' => null, 'page' => '1', 'per_page' => null, 'orderings' => 'DESC'] as $field => $value) { yield [ListNotices::class, array_replace($data, [$field => $value])]; }
        foreach ([[0, 1, []], [-1, 1, []], [1, 0, []], [1, 33, []], [1000002, 1, []], [PHP_INT_MAX, 32, []], [1, 1, ['recorded_at' => 'drop table']], [1, 1, ['recorded_at' => true]], [1, 1, ['position' => 'ASC']], [1, 1, ['recorded_at;DROP TABLE fight_notices' => 'ASC']]] as [$page, $perPage, $order]) {
            yield [ListNotices::class, array_replace($data, ['page' => $page, 'per_page' => $perPage, 'orderings' => $order])];
        }
    }
}
