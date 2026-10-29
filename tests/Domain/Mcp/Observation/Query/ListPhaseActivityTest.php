<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Observation\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Observation\Query\ListPhaseActivity;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ListPhaseActivity::class)]
final class ListPhaseActivityTest extends UnitTestCase
{
    public function test_that_query_retains_exact_selection_and_normalized_finite_pagination_without_authority(): void
    {
        $query = new ListPhaseActivity('project', 'session', 'phase', 2, 10, ['model_id' => 'model', 'kind' => 'consumer/call', 'producer_id' => 'producer'], ['recorded_at' => 'asc']);
        self::assertSame(10, $query->pagination()->offset());
        self::assertSame('phase', $query->phaseId());
        self::assertSame(['project'], $query->execution()->selection()->projects());
        self::assertSame(['session_id' => 'session'], $query->execution()->selection()->filters());
        self::assertSame(['kind' => 'consumer/call', 'model_id' => 'model', 'producer_id' => 'producer'], $query->filters());
        self::assertSame($query->toArray(), ListPhaseActivity::fromArray($query->toArray())->toArray());
        self::assertSame(100, (new ListPhaseActivity('project', 'session', 'phase'))->pagination()->limit());
        self::assertSame(1000000, (new ListPhaseActivity('project', 'session', 'phase', 1000001, 1))->pagination()->offset());
    }

    #[DataProvider('invalidInputs')]
    public function test_that_invalid_query_inputs_reject_safely(array $arguments): void
    {
        try { new ListPhaseActivity('project', 'session', 'phase', ...$arguments); self::fail('Invalid input must reject.'); }
        catch (DomainException $exception) { self::assertStringNotContainsString('PROTECTED-SENTINEL', $exception->getMessage()); }
    }

    public static function invalidInputs(): iterable
    {
        yield [ ['page' => 0] ];
        yield [ ['perPage' => 0] ];
        yield [ ['perPage' => 101] ];
        yield [ ['page' => 1000002, 'perPage' => 1] ];
        yield [ ['page' => PHP_INT_MAX, 'perPage' => PHP_INT_MAX] ];
        yield [ ['filters' => ['body' => 'PROTECTED-SENTINEL']] ];
        yield [ ['filters' => ['kind' => 'PROTECTED-SENTINEL']] ];
        yield [ ['filters' => ['producer_id' => 'invalid producer']] ];
        yield [ ['orderings' => ['body' => 'ASC']] ];
        yield [ ['orderings' => ['recorded_at' => 'sideways']] ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_that_noncanonical_payloads_and_authority_reject(array $changes): void
    {
        $this->expectException(DomainException::class);
        ListPhaseActivity::fromArray(array_replace((new ListPhaseActivity('project', 'session', 'phase'))->toArray(), $changes));
    }

    public static function invalidPayloads(): iterable
    {
        yield [['page' => '1']]; yield [['per_page' => null]];
        yield [['filters' => 'kind']]; yield [['orderings' => null]];
        yield [['phase_id' => 'invalid phase']]; yield [['session_id' => null]];
        yield [['project_id' => 'invalid project']]; yield [['grants' => 'all']];
    }
}
