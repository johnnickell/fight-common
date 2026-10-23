<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Execution\Usage;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Usage\UsageCompleteness;
use Fight\Common\Domain\Execution\Usage\UsageMeasurement;
use Fight\Common\Domain\Execution\Usage\UsageTotal;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UsageTotal::class)]
final class UsageTotalTest extends UnitTestCase
{
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
