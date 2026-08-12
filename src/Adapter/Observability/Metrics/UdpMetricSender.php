<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Observability\Metrics;

use Closure;
use Socket;

/**
 * Class UdpMetricSender
 *
 * Sends one metric line over a UDP socket.
 *
 * @internal
 */
final readonly class UdpMetricSender
{
    /**
     * @var Closure(): (Socket|false)
     */
    private Closure $socketFactory;

    /**
     * Constructs UdpMetricSender
     *
     * @param string                           $host
     * @param integer                          $port
     * @param Closure(): (Socket|false)|null $socketFactory
     */
    public function __construct(
        private string $host,
        private int $port,
        ?Closure $socketFactory = null
    ) {
        $this->socketFactory = $socketFactory ?? static fn (): Socket|false => @socket_create(
            AF_INET,
            SOCK_DGRAM,
            SOL_UDP
        );
    }

    /**
     * Sends one metric line
     */
    public function send(string $metric): void
    {
        $socket = ($this->socketFactory)();

        if ($socket === false) {
            return;
        }

        @socket_sendto($socket, $metric, strlen($metric), 0, $this->host, $this->port);
        socket_close($socket);
    }
}
