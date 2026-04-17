<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mail\Logging;

use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LoggingMailTransport
 */
final readonly class LoggingMailTransport implements MailTransport
{
    /**
     * Constructs LoggingMailTransport
     */
    public function __construct(
        private MailTransport $transport,
        private LoggerInterface $logger,
        private string $logLevel = LogLevel::DEBUG
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(MailMessage $message): void
    {
        $this->logger->log($this->logLevel, '[Email]: Outgoing Mail Message', [
            'subject'         => $message->getSubject(),
            'from'            => $message->getFrom(),
            'to'              => $message->getTo(),
            'reply_to'        => $message->getReplyTo(),
            'cc'              => $message->getCc(),
            'bcc'             => $message->getBcc(),
            'sender'          => $message->getSender(),
            'return_path'     => $message->getReturnPath(),
            'charset'         => $message->getCharset(),
            'priority'        => $message->getPriority()->name(),
            'timestamp'       => $message->getTimestamp(),
            'max_line_length' => $message->getMaxLineLength()
        ]);

        $this->transport->send($message);
    }
}
