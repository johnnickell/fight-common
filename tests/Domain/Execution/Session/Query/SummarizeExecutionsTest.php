<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Execution\Session\Query;

use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Execution\Session\Query\SummarizeExecutions;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SummarizeExecutions::class)]
final class SummarizeExecutionsTest extends UnitTestCase
{
    public function test_that_query_roundtrips_only_selection_without_caller_authority(): void
    {
        $selection = ExecutionSelection::create(['project'], ['consumer_reference' => 'build:150']);
        $query = new SummarizeExecutions($selection);
        self::assertSame($selection, $query->selection());
        self::assertSame([
            'projects' => ['project'],
            'filters' => ['consumer_reference' => 'build:150']
        ], $query->toArray());
        self::assertSame($query->toArray(), SummarizeExecutions::fromArray($query->toArray())->toArray());
    }
}
