<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Sms\Twilio;

use Fight\Common\Application\Sms\Exception\SmsException;
use Fight\Common\Application\Sms\Message\SmsMessage;
use Fight\Common\Application\Sms\Transport\SmsTransport;
use Throwable;
use Twilio\Rest\Client;

/**
 * Class TwilioSmsTransport
 */
final readonly class TwilioSmsTransport implements SmsTransport
{
    private Client $twilioClient;

    /**
     * Constructs TwilioSmsTransport
     */
    public function __construct(Client $client)
    {
        $this->twilioClient = $client;
    }

    /**
     * @inheritDoc
     */
    public function send(SmsMessage $message): void
    {
        $messageOptions = [
            'from' => $message->getFrom()
        ];

        if ($message->getBody() !== null) {
            $messageOptions['body'] = $message->getBody();
        }

        $media = $message->getMedia();

        if ($media !== []) {
            $messageOptions['mediaUrl'] = array_map(strval(...), $media);
        }

        try {
            $this->twilioClient->messages->create($message->getTo(), $messageOptions);
        } catch (Throwable $throwable) {
            throw new SmsException(
                $throwable->getMessage(),
                (int) $throwable->getCode(),
                $throwable
            );
        }
    }
}
