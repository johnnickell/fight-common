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

        /** @var MockInterface|MetricsCollector $metricsCollector */
        $metricsCollector = $this->mock(MetricsCollector::class);
        $metricsCollector->shouldReceive('increment')
            ->once()
            ->withArgs(fn(string $metricName, array $tags): bool => $metricName === 'query.executed' && isset($tags['type']));
        $metricsCollector->shouldReceive('histogram')
            ->once()
            ->withArgs(fn(string $metricName): bool => $metricName === 'query.latency_ms');

        $filter = new MetricsQueryFilter($metricsCollector);
        $filter->process($message, function (QueryMessage $queryMessage): void {});
    }

    public function test_that_process_emits_failed_and_rethrows_on_exception(): void
    {
        $message = QueryMessage::create(new SampleMetricsQuery());

        /** @var MockInterface|MetricsCollector $metricsCollector */
        $metricsCollector = $this->mock(MetricsCollector::class);
        $metricsCollector->shouldReceive('increment')
            ->once()
            ->withArgs(fn(string $metricName, array $tags): bool => $metricName === 'query.failed' && isset($tags['exception']));
        $metricsCollector->shouldReceive('histogram')
            ->once()
            ->withArgs(fn(string $metricName): bool => $metricName === 'query.latency_ms');

        $filter = new MetricsQueryFilter($metricsCollector);

        $this->expectException(RuntimeException::class);
        $filter->process($message, fn(QueryMessage $queryMessage): never => throw new RuntimeException('oops'));
    }
}

class SampleMetricsQuery implements Query
{
    public static function fromArray(array $data): static { return new static(); }

    public function toArray(): array { return []; }
}
