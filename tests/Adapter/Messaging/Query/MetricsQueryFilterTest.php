<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Query;

use Fight\Common\Adapter\Messaging\Query\MetricsQueryFilter;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(MetricsQueryFilter::class)]
class MetricsQueryFilterTest extends UnitTestCase
{
    public function test_that_process_emits_executed_and_latency_on_success(): void
    {
        $message = QueryMessage::create(new SampleMetricsQuery());

        /** @var MockInterface|MetricsCollector $metrics */
        $metrics = $this->mock(MetricsCollector::class);
        $metrics->shouldReceive('increment')
            ->once()
            ->withArgs(fn(string $m, array $t): bool => $m === 'query.executed' && isset($t['type']));
        $metrics->shouldReceive('histogram')
            ->once()
            ->withArgs(fn(string $m): bool => $m === 'query.latency_ms');

        $filter = new MetricsQueryFilter($metrics);
        $filter->process($message, function (QueryMessage $msg): void {});
    }

    public function test_that_process_emits_failed_and_rethrows_on_exception(): void
    {
        $message = QueryMessage::create(new SampleMetricsQuery());

        /** @var MockInterface|MetricsCollector $metrics */
        $metrics = $this->mock(MetricsCollector::class);
        $metrics->shouldReceive('increment')
            ->once()
            ->withArgs(fn(string $m, array $t): bool => $m === 'query.failed' && isset($t['exception']));
        $metrics->shouldReceive('histogram')
            ->once()
            ->withArgs(fn(string $m): bool => $m === 'query.latency_ms');

        $filter = new MetricsQueryFilter($metrics);

        $this->expectException(RuntimeException::class);
        $filter->process($message, fn(QueryMessage $msg): never => throw new RuntimeException('oops'));
    }
}

class SampleMetricsQuery implements Query
{
    public static function fromArray(array $data): static { return new static(); }

    public function toArray(): array { return []; }
}
