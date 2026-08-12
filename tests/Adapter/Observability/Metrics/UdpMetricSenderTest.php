<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Observability\Metrics;

use Fight\Common\Adapter\Observability\Metrics\UdpMetricSender;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Socket;

#[CoversClass(UdpMetricSender::class)]
class UdpMetricSenderTest extends UnitTestCase
{
    public function test_that_send_returns_silently_when_socket_creation_fails(): void
    {
        $socketCreationAttempts = 0;
        $sender = new UdpMetricSender(
            '127.0.0.1',
            8125,
            static function () use (&$socketCreationAttempts): false {
                ++$socketCreationAttempts;

                return false;
            }
        );

        $sender->send('app.command.executed:1|c');

        self::assertSame(1, $socketCreationAttempts);
    }

    public function test_that_send_writes_the_known_metric_to_a_udp_socket(): void
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        self::assertInstanceOf(Socket::class, $socket);

        $sender = new UdpMetricSender(
            '127.0.0.1',
            8125,
            static fn (): Socket => $socket
        );

        $sender->send('app.command.executed:1|c');
    }
}
