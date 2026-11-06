<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Command;

use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Throwable;

/**
 * Class MetricsCommandFilter
 */
final readonly class MetricsCommandFilter implements CommandFilter
{
    private MetricsCollector $metricsCollector;

    /**
     * Constructs MetricsCommandFilter
     */
    public function __construct(MetricsCollector $metrics)
    {
        $this->metricsCollector = $metrics;
    }

    /**
     * @inheritDoc
     */
    public function process(CommandMessage $commandMessage, callable $next): void
    {
        $commandClass = $commandMessage->payloadType()->toClassName();
        $tags = ['type' => $commandClass];
        $startedAtNanoseconds = hrtime(true);

        try {
            $next($commandMessage);
            $elapsedMilliseconds = (hrtime(true) - $startedAtNanoseconds) / 1e6;
            $this->metricsCollector->increment('command.executed', $tags);
            $this->metricsCollector->histogram('command.latency_ms', $elapsedMilliseconds, $tags);
        } catch (Throwable $throwable) {
            $elapsedMilliseconds = (hrtime(true) - $startedAtNanoseconds) / 1e6;
            $this->metricsCollector->increment('command.failed', $tags + ['exception' => $throwable::class]);
            $this->metricsCollector->histogram('command.latency_ms', $elapsedMilliseconds, $tags);
            throw $throwable;
        }
    }
}
