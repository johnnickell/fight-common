<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Sms\Logging;

use Fight\Common\Application\Sms\Message\SmsMessage;
use Fight\Common\Application\Sms\Transport\SmsTransport;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LoggingSmsTransport
 */
final readonly class LoggingSmsTransport implements SmsTransport
{
    /**
     * Constructs LoggingSmsTransport
     */
    public function __construct(
        private SmsTransport $transport,
        private LoggerInterface $logger,
        private string $logLevel = LogLevel::DEBUG
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(SmsMessage $message): void
    {
        $this->logger->log($this->logLevel, '[SMS]: Outgoing SMS Message', [
            'to'          => $message->getTo(),
            'from'        => $message->getFrom(),
            'body'        => $message->getBody(),
            'media_count' => count($message->getMedia()),
        ]);

        $this->transport->send($message);
    }
}
