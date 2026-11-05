<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Command;

use Fight\Common\Adapter\Messaging\Command\MetricsCommandFilter;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(MetricsCommandFilter::class)]
class MetricsCommandFilterTest extends UnitTestCase
{
    public function test_that_process_emits_executed_and_latency_on_success(): void
    {
        $message = CommandMessage::create(new SampleMetricsCommand());

        /** @var MockInterface|MetricsCollector $metricsCollector */
        $metricsCollector = $this->mock(MetricsCollector::class);
        $metricsCollector->shouldReceive('increment')
            ->once()
            ->withArgs(fn(string $metricName, array $tags): bool => $metricName === 'command.executed' && isset($tags['type']));
        $metricsCollector->shouldReceive('histogram')
            ->once()
            ->withArgs(fn(string $metricName): bool => $metricName === 'command.latency_ms');

        $filter = new MetricsCommandFilter($metricsCollector);
        $filter->process($message, function (CommandMessage $commandMessage): void {});
    }

    public function test_that_process_emits_failed_and_rethrows_on_exception(): void
    {
        $message = CommandMessage::create(new SampleMetricsCommand());

        /** @var MockInterface|MetricsCollector $metricsCollector */
        $metricsCollector = $this->mock(MetricsCollector::class);
        $metricsCollector->shouldReceive('increment')
            ->once()
            ->withArgs(fn(string $metricName, array $tags): bool => $metricName === 'command.failed' && isset($tags['exception']));
        $metricsCollector->shouldReceive('histogram')
            ->once()
            ->withArgs(fn(string $metricName): bool => $metricName === 'command.latency_ms');

        $filter = new MetricsCommandFilter($metricsCollector);

        $this->expectException(RuntimeException::class);
        $filter->process($message, fn(CommandMessage $commandMessage): never => throw new RuntimeException('oops'));
    }
}

class SampleMetricsCommand implements Command
{
    public static function fromArray(array $data): static { return new static(); }

    public function toArray(): array { return []; }
}
