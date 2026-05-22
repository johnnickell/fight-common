<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mail\Logging;

use Mockery;
use Fight\Common\Adapter\Mail\Logging\LoggingMailTransport;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Message\Priority;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

#[CoversClass(LoggingMailTransport::class)]
class LoggingMailTransportTest extends UnitTestCase
{
    public function test_that_send_logs_and_delegates(): void
    {
        $message = MailMessage::create()
            ->setSubject('Test Subject')
            ->addFrom('from@example.com', 'From')
            ->addTo('to@example.com', 'To')
            ->addReplyTo('reply@example.com')
            ->addCc('cc@example.com')
            ->addBcc('bcc@example.com')
            ->addContent('<html/>', MailMessage::CONTENT_TYPE_HTML)
            ->addContent('text', MailMessage::CONTENT_TYPE_PLAIN)
            ->setSender('sender@example.com')
            ->setReturnPath('bounce@example.com')
            ->setTimestamp(1700000000)
            ->setPriority(Priority::HIGH);

        /** @var MockInterface|MailTransport $transport */
        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->once()->with(
            LogLevel::DEBUG,
            '[Email]: Outgoing Mail Message',
            [
                'subject'         => 'Test Subject',
                'from'            => [['address' => 'from@example.com', 'name' => 'From']],
                'to'              => [['address' => 'to@example.com', 'name' => 'To']],
                'reply_to'        => [['address' => 'reply@example.com', 'name' => null]],
                'cc'              => [['address' => 'cc@example.com', 'name' => null]],
                'bcc'             => [['address' => 'bcc@example.com', 'name' => null]],
                'sender'          => ['address' => 'sender@example.com', 'name' => null],
                'return_path'     => 'bounce@example.com',
                'charset'         => MailMessage::DEFAULT_CHARSET,
                'priority'        => Priority::HIGH->name,
                'timestamp'       => 1700000000,
                'max_line_length' => null,
            ]
        );

        $loggingTransport = new LoggingMailTransport($transport, $logger);
        $loggingTransport->send($message);
    }

    public function test_that_send_uses_custom_log_level(): void
    {
        $message = MailMessage::create();

        /** @var MockInterface|MailTransport $transport */
        $transport = $this->mock(MailTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->once()->with(
            LogLevel::ERROR,
            '[Email]: Outgoing Mail Message',
            Mockery::type('array')
        );

        $loggingTransport = new LoggingMailTransport($transport, $logger, LogLevel::ERROR);
        $loggingTransport->send($message);
    }
}
