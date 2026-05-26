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

        /** @var MockInterface|MetricsCollector $metrics */
        $metrics = $this->mock(MetricsCollector::class);
        $metrics->shouldReceive('increment')
            ->once()
            ->withArgs(fn(string $m, array $t): bool => $m === 'command.executed' && isset($t['type']));
        $metrics->shouldReceive('histogram')
            ->once()
            ->withArgs(fn(string $m): bool => $m === 'command.latency_ms');

        $filter = new MetricsCommandFilter($metrics);
        $filter->process($message, function (CommandMessage $msg): void {});
    }

    public function test_that_process_emits_failed_and_rethrows_on_exception(): void
    {
        $message = CommandMessage::create(new SampleMetricsCommand());

        /** @var MockInterface|MetricsCollector $metrics */
        $metrics = $this->mock(MetricsCollector::class);
        $metrics->shouldReceive('increment')
            ->once()
            ->withArgs(fn(string $m, array $t): bool => $m === 'command.failed' && isset($t['exception']));
        $metrics->shouldReceive('histogram')
            ->once()
            ->withArgs(fn(string $m): bool => $m === 'command.latency_ms');

        $filter = new MetricsCommandFilter($metrics);

        $this->expectException(RuntimeException::class);
        $filter->process($message, fn(CommandMessage $msg): never => throw new RuntimeException('oops'));
    }
}

class SampleMetricsCommand implements Command
{
    public static function fromArray(array $data): static { return new static(); }
    public function toArray(): array { return []; }
}
