<?php

declare(strict_types=1);

namespace Fight\Common\Application\Sms\Message;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Internet\Url;

/**
 * Interface SmsFactory
 */
interface SmsFactory
{
    /**
     * Creates an SMS message
     *
     * @param string       $to        The recipient phone number
     * @param string       $from      The sender phone number
     * @param string|null  $body      The message body
     * @param array<Url|string> $mediaUrls Media URLs (Url objects or URL strings)
     *
     * @throws DomainException When any of the URLs are not valid
     */
    public function createMessage(
        string $to,
        string $from,
        ?string $body = null,
        array $mediaUrls = []
    ): SmsMessage;

    /**
     * Creates a media URL
     *
     * @throws DomainException When the URL is not valid
     */
    public function createMediaUrl(string $url): Url;
}
