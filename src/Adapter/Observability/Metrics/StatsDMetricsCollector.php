<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Observability\Metrics;

use Closure;
use Fight\Common\Application\Observability\MetricsCollector;

/**
 * Class StatsDMetricsCollector
 *
 * Sends metrics over UDP using the StatsD/DogStatsD wire protocol.
 * Requires ext-sockets.
 */
final readonly class StatsDMetricsCollector implements MetricsCollector
{
    private Closure $sender;

    /**
     * Constructs StatsDMetricsCollector
     *
     * $sender is optional and injectable for testing; defaults to a UDP socket sender
     */
    public function __construct(
        private string $host,
        private int $port = 8125,
        private string $prefix = '',
        ?Closure $sender = null
    ) {
        $this->sender = $sender ?? new UdpMetricSender($this->host, $this->port)->send(...);
    }

    /**
     * @inheritDoc
     */
    public function increment(string $metric, array $tags = []): void
    {
        ($this->sender)($this->format($metric, '1', 'c', $tags));
    }

    /**
     * @inheritDoc
     */
    public function gauge(string $metric, float $value, array $tags = []): void
    {
        ($this->sender)($this->format($metric, (string) $value, 'g', $tags));
    }

    /**
     * @inheritDoc
     */
    public function histogram(string $metric, float $value, array $tags = []): void
    {
        ($this->sender)($this->format($metric, (string) $value, 'ms', $tags));
    }

    /**
     * Formats a metric line in DogStatsD wire format
     *
     * @param string $metric
     * @param string $value
     * @param string $type
     * @param array<string, string> $tags
     */
    private function format(string $metric, string $value, string $type, array $tags): string
    {
        $name = $this->prefix !== '' ? $this->prefix.'.'.$metric : $metric;
        $line = sprintf('%s:%s|%s', $name, $value, $type);

        if (!empty($tags)) {
            $tagParts = [];
            foreach ($tags as $key => $val) {
                $tagParts[] = $key.':'.$val;
            }

            $line .= '|#'.implode(',', $tagParts);
        }

        return $line;
    }
}
