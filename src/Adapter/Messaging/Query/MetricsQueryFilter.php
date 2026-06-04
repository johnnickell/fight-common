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
    /**
     * Constructs MetricsQueryFilter
     */
    public function __construct(private MetricsCollector $metrics)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(QueryMessage $queryMessage, callable $next): void
    {
        $type = $queryMessage->payloadType()->toClassName();
        $tags = ['type' => $type];
        $start = hrtime(true);

        try {
            $next($queryMessage);
            $elapsed = (hrtime(true) - $start) / 1e6;
            $this->metrics->increment('query.executed', $tags);
            $this->metrics->histogram('query.latency_ms', $elapsed, $tags);
        } catch (Throwable $throwable) {
            $elapsed = (hrtime(true) - $start) / 1e6;
            $this->metrics->increment('query.failed', $tags + ['exception' => $throwable::class]);
            $this->metrics->histogram('query.latency_ms', $elapsed, $tags);
            throw $throwable;
        }
    }
}
