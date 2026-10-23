<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Execution\Read;

use Fight\Common\Application\Execution\Read\ExecutionAccess;
use Fight\Common\Application\Execution\Read\ExecutionSelection;
use Fight\Common\Application\Execution\Read\Query\ListExecutions;
use Fight\Common\Application\Execution\Read\Query\SummarizeExecutions;
use Fight\Common\Application\Execution\Read\QueryHandler\ListExecutionsHandler;
use Fight\Common\Application\Execution\Read\QueryHandler\SummarizeExecutionsHandler;
use Fight\Common\Application\Execution\Read\UsageTotal;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Usage\UsageCompleteness;
use Fight\Common\Domain\Execution\Usage\UsageMeasurement;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ExecutionAccess::class)]
#[CoversClass(ExecutionSelection::class)]
#[CoversClass(ListExecutions::class)]
#[CoversClass(SummarizeExecutions::class)]
#[CoversClass(ListExecutionsHandler::class)]
#[CoversClass(SummarizeExecutionsHandler::class)]
#[CoversClass(UsageTotal::class)]
final class ExecutionReadValuesTest extends UnitTestCase
{
    public function test_that_query_roundtrips_preserve_selection_without_serializing_authority(): void
    {
        $projects = ['z', 'a', 'a'];
        $value = 'session';
        $selection = ExecutionSelection::create($projects, ['session_id' => &$value]);
        $value = 'changed';
        $projects[0] = 'changed';
        self::assertSame(['a', 'z'], $selection->projects());
        self::assertSame(['session_id' => 'session'], $selection->filters());
        $summary = new SummarizeExecutions($selection);
        self::assertSame($summary->toArray(), SummarizeExecutions::fromArray($summary->toArray())->toArray());
        $list = new ListExecutions($selection, 2, 20, ['latest_recorded_at' => 'asc']);
        self::assertSame($list->toArray(), ListExecutions::fromArray($list->toArray())->toArray());
        self::assertSame($selection, $summary->selection());
        self::assertSame($selection, $list->selection());
        self::assertSame(20, $list->pagination()->offset());
        self::assertSame(ListExecutions::class, ListExecutionsHandler::queryRegistration());
        self::assertSame(SummarizeExecutions::class, SummarizeExecutionsHandler::queryRegistration());
        $session = 'a';
        $grants = ExecutionAccess::create(['project' => [&$session, 'a'], 'empty' => [], 'all' => null, '123' => ['456']]);
        $session = 'changed';
        self::assertSame(['project' => ['a'], 'empty' => [], 'all' => null, 123 => ['456']], $grants->projects());
    }

    #[DataProvider('invalidSelections')]
    public function test_that_selection_rejects_unsupported_filters_and_unbounded_or_malformed_projects(array $projects, array $filters): void
    {
        $this->expectException(DomainException::class);
        ExecutionSelection::create($projects, $filters);
    }

    public static function invalidSelections(): iterable
    {
        yield [['key' => 'project'], []];
        yield [array_fill(0, 17, 'project'), []];
        yield [['unsafe body'], []];
        yield [['project'], ['payload' => 'secret']];
        yield [['project'], ['session_id' => [], 'consumer_reference' => 'x']];
        yield [['project'], ['a' => 1, 'b' => 2, 'c' => 3]];
    }

    #[DataProvider('invalidAccess')]
    public function test_that_trusted_access_still_requires_finite_valid_scope(array $access): void
    {
        $this->expectException(DomainException::class);
        ExecutionAccess::create($access);
    }

    public static function invalidAccess(): iterable
    {
        yield [array_fill_keys(range('a', 'q'), null)];
        yield [['invalid project' => null]];
        yield [['project' => 'session']];
        yield [['project' => ['key' => 'session']]];
        yield [['project' => array_fill(0, 101, 'session')]];
        yield [['project' => ['invalid session']]];
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

    #[DataProvider('invalidPayloads')]
    public function test_that_payloads_reject_noncanonical_shapes(string $type, array $data): void
    {
        $this->expectException(DomainException::class);
        $type::fromArray($data);
    }

    public static function invalidPayloads(): iterable
    {
        yield [SummarizeExecutions::class, ['projects' => [], 'filters' => [], 'access' => []]];
        yield [SummarizeExecutions::class, ['projects' => 'project', 'filters' => []]];
        yield [ListExecutions::class, ['selection' => [], 'page' => '1', 'per_page' => 10, 'orderings' => []]];
    }

    public function test_that_usage_totals_keep_exact_zero_missing_and_mixed_completeness_distinct(): void
    {
        $unknown = UsageTotal::fromMeasurement(UsageMeasurement::create('provider/output', 'token', 'source', UsageCompleteness::UNKNOWN, null));
        $zero = UsageTotal::fromMeasurement(UsageMeasurement::create('provider/output', 'token', 'source', UsageCompleteness::COMPLETE, '0'));
        $partial = UsageTotal::fromMeasurement(UsageMeasurement::create('provider/output', 'token', 'source', UsageCompleteness::PARTIAL, '0.1'));
        self::assertNull($unknown->plus($unknown)->toArray()['measured']);
        self::assertSame('0', $unknown->plus($zero)->toArray()['measured']);
        self::assertSame('0', $zero->plus($unknown)->toArray()['measured']);
        self::assertSame('0.2', $partial->plus($partial)->toArray()['measured']);
        self::assertSame(['complete' => 1, 'partial' => 1, 'unknown' => 1, 'unavailable' => 0], $unknown->plus($zero)->plus($partial)->toArray()['counts']);
        $different = UsageTotal::fromMeasurement(UsageMeasurement::create('provider/output', 'USD', 'source', UsageCompleteness::COMPLETE, '1'));
        $this->expectException(DomainException::class);
        $zero->plus($different);
    }
}
