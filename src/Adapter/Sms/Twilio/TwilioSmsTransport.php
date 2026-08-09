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
    /**
     * Constructs TwilioSmsTransport
     */
    public function __construct(private Client $client)
    {
    }

    /**
     * @inheritDoc
     */
    public function send(SmsMessage $message): void
    {
        $args = [
            'from' => $message->getFrom()
        ];

        if ($message->getBody() !== null) {
            $args['body'] = $message->getBody();
        }

        $media = $message->getMedia();

        if ($media !== []) {
            $args['mediaUrl'] = array_map(strval(...), $media);
        }

        try {
            $this->client->messages->create($message->getTo(), $args);
        } catch (Throwable $throwable) {
            throw new SmsException(
                $throwable->getMessage(),
                (int) $throwable->getCode(),
                $throwable
            );
        }
    }
}
