<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Sms\Logging;

use Mockery;
use Fight\Common\Adapter\Sms\Logging\LoggingSmsTransport;
use Fight\Common\Application\Sms\Message\SmsMessage;
use Fight\Common\Application\Sms\Transport\SmsTransport;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

#[CoversClass(LoggingSmsTransport::class)]
class LoggingSmsTransportTest extends UnitTestCase
{
    public function test_that_send_logs_and_delegates(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321')
            ->setBody('Hello, World!');

        /** @var MockInterface|SmsTransport $transport */
        $transport = $this->mock(SmsTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->once()->with(
            LogLevel::DEBUG,
            '[SMS]: Outgoing SMS Message',
            [
                'to'          => '+1234567890',
                'from'        => '+0987654321',
                'body'        => 'Hello, World!',
                'media_count' => 0,
            ]
        );

        $loggingTransport = new LoggingSmsTransport($transport, $logger);
        $loggingTransport->send($message);
    }

    public function test_that_send_uses_custom_log_level(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321');

        /** @var MockInterface|SmsTransport $transport */
        $transport = $this->mock(SmsTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->once()->with(
            LogLevel::ERROR,
            '[SMS]: Outgoing SMS Message',
            Mockery::type('array')
        );

        $loggingTransport = new LoggingSmsTransport($transport, $logger, LogLevel::ERROR);
        $loggingTransport->send($message);
    }
}
