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
final class MetricsCommandFilter implements CommandFilter
{
    /**
     * Constructs MetricsCommandFilter
     */
    public function __construct(private MetricsCollector $metrics)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(CommandMessage $commandMessage, callable $next): void
    {
        $type = $commandMessage->payloadType()->toClassName();
        $tags = ['type' => $type];
        $start = hrtime(true);

        try {
            $next($commandMessage);
            $elapsed = (hrtime(true) - $start) / 1e6;
            $this->metrics->increment('command.executed', $tags);
            $this->metrics->histogram('command.latency_ms', $elapsed, $tags);
        } catch (Throwable $e) {
            $elapsed = (hrtime(true) - $start) / 1e6;
            $this->metrics->increment('command.failed', $tags + ['exception' => $e::class]);
            $this->metrics->histogram('command.latency_ms', $elapsed, $tags);
            throw $e;
        }
    }
}
