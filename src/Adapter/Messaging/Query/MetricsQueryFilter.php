<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Query;

use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Throwable;

/**
 * Class MetricsQueryFilter
 */
final readonly class MetricsQueryFilter implements QueryFilter
{
    private MetricsCollector $metricsCollector;

    /**
     * Constructs MetricsQueryFilter
     */
    public function __construct(MetricsCollector $metrics)
    {
        $this->metricsCollector = $metrics;
    }

    /**
     * @inheritDoc
     */
    public function process(QueryMessage $queryMessage, callable $next): void
    {
        $queryClass = $queryMessage->payloadType()->toClassName();
        $tags = ['type' => $queryClass];
        $startedAtNanoseconds = hrtime(true);

        try {
            $next($queryMessage);
            $elapsedMilliseconds = (hrtime(true) - $startedAtNanoseconds) / 1e6;
            $this->metricsCollector->increment('query.executed', $tags);
            $this->metricsCollector->histogram('query.latency_ms', $elapsedMilliseconds, $tags);
        } catch (Throwable $throwable) {
            $elapsedMilliseconds = (hrtime(true) - $startedAtNanoseconds) / 1e6;
            $this->metricsCollector->increment('query.failed', $tags + ['exception' => $throwable::class]);
            $this->metricsCollector->histogram('query.latency_ms', $elapsedMilliseconds, $tags);
            throw $throwable;
        }
    }
}
