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
        $host = $this->host;
        $port = $this->port;

        $this->sender = $sender ?? static function (string $metric) use ($host, $port): void {
            $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

            if ($socket === false) {
                return; // @codeCoverageIgnore
            }

            @socket_sendto($socket, $metric, strlen($metric), 0, $host, $port);
            socket_close($socket);
        };
    }

    /**
     * @param string $metric
     * @param array<string, string> $tags
     *
     * @inheritDoc
     */
    public function increment(string $metric, array $tags = []): void
    {
        ($this->sender)($this->format($metric, '1', 'c', $tags));
    }

    /**
     * @param string $metric
     * @param float $value
     * @param array<string, string> $tags
     *
     * @inheritDoc
     */
    public function gauge(string $metric, float $value, array $tags = []): void
    {
        ($this->sender)($this->format($metric, (string) $value, 'g', $tags));
    }

    /**
     * @param string $metric
     * @param float $value
     * @param array<string, string> $tags
     *
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
