<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Execution\Phase\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Execution\Session\Query\GetExecution;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ListExecutionPhases::class)]
#[CoversClass(GetExecution::class)]
final class ListExecutionPhasesTest extends UnitTestCase
{
    public function test_that_query_roundtrips_exact_selection_filters_and_strict_normalized_page_without_grants(): void
    {
        $query = new ListExecutionPhases('project', 'session', 2, 10, ['name' => 'review', 'agent_id' => 'agent'], ['latest_recorded_at' => 'asc']);
        self::assertSame(10, $query->pagination()->offset());
        self::assertSame(['agent_id' => 'agent', 'name' => 'review'], $query->filters());
        self::assertSame(['project'], $query->execution()->selection()->projects());
        self::assertSame(['session_id' => 'session'], $query->execution()->selection()->filters());
        self::assertSame($query->toArray(), ListExecutionPhases::fromArray($query->toArray())->toArray());
        $selected = new GetExecution('project', 'session');
        self::assertSame(['project_id' => 'project', 'session_id' => 'session'], $selected->toArray());
        self::assertSame($selected->toArray(), GetExecution::fromArray($selected->toArray())->toArray());
        self::assertSame(100, (new ListExecutionPhases('project', 'session'))->pagination()->limit());
        self::assertSame(1000000, (new ListExecutionPhases('project', 'session', 1000001, 1))->pagination()->offset());
    }

    #[DataProvider('invalidPages')]
    public function test_that_invalid_bounds_filters_and_orderings_reject_without_echoing_inputs(?int $page, ?int $size, array $filters, array $orderings): void
    {
        $this->expectException(DomainException::class);
        new ListExecutionPhases('project', 'session', $page, $size, $filters, $orderings);
    }

    public static function invalidPages(): iterable
    {
        yield [0, 1, [], []];
        yield [1, 0, [], []];
        yield [1, 101, [], []];
        yield [1000002, 1, [], []];
        yield [PHP_INT_MAX, PHP_INT_MAX, [], []];
        yield [1, 1, ['body' => 'PROTECTED-SENTINEL'], []];
        yield [1, 1, ['name' => 'invalid name'], []];
        yield [1, 1, [], ['payload; DROP TABLE x' => 'ASC']];
        yield [1, 1, [], ['latest_recorded_at' => 'sideways']];
    }

    #[DataProvider('invalidPayloads')]
    public function test_that_noncanonical_payloads_reject(array $changes): void
    {
        $this->expectException(DomainException::class);
        ListExecutionPhases::fromArray(array_replace((new ListExecutionPhases('project', 'session'))->toArray(), $changes));
    }

    public static function invalidPayloads(): iterable
    {
        yield [['page' => '1']];
        yield [['per_page' => null]];
        yield [['filters' => 'name']];
        yield [['orderings' => null]];
        yield [['project_id' => 'invalid project']];
        yield [['session_id' => null]];
        yield [['authority' => 'all']];
    }

    public function test_that_selected_execution_rejects_extra_authority(): void
    {
        $this->expectException(DomainException::class);
        GetExecution::fromArray(['project_id' => 'project', 'session_id' => 'session', 'access' => 'all']);
    }
}
