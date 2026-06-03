<?php

declare(strict_types=1);

namespace Fight\Common\Application\Sms\Transport;

use Fight\Common\Application\Sms\Exception\SmsException;
use Fight\Common\Application\Sms\Message\SmsMessage;

/**
 * Interface SmsTransport
 */
interface SmsTransport
{
    /**
     * Sends an SMS message
     *
     * @throws SmsException When an error occurs
     */
    public function send(SmsMessage $message): void;
}
