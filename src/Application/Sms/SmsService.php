<?php

declare(strict_types=1);

namespace Fight\Common\Application\Sms;

use Fight\Common\Application\Sms\Message\SmsFactory;
use Fight\Common\Application\Sms\Message\SmsMessage;
use Fight\Common\Application\Sms\Transport\SmsTransport;
use Fight\Common\Domain\Value\Internet\Url;

/**
 * Class SmsService
 */
final readonly class SmsService implements SmsTransport, SmsFactory
{
    /**
     * Constructs SmsService
     */
    public function __construct(private SmsTransport $transport)
    {
    }

    /**
     * @inheritDoc
     */
    public function send(SmsMessage $message): void
    {
        $this->transport->send($message);
    }

    /**
     * @inheritDoc
     */
    public function createMessage(
        string $to,
        string $from,
        ?string $body = null,
        array $mediaUrls = []
    ): SmsMessage {
        $message = SmsMessage::create($to, $from);

        if ($body !== null) {
            $message->setBody($body);
        }

        foreach ($mediaUrls as $url) {
            if ($url instanceof Url) {
                $message->addMedia($url);
            } else {
                $message->addMedia($this->createMediaUrl($url));
            }
        }

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function createMediaUrl(string $url): Url
    {
        return Url::parse($url);
    }
}
