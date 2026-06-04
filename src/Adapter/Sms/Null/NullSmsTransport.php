<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Sms\Null;

use Fight\Common\Application\Sms\Message\SmsMessage;
use Fight\Common\Application\Sms\Transport\SmsTransport;

/**
 * Class NullSmsTransport
 */
final class NullSmsTransport implements SmsTransport
{
    /**
     * @inheritDoc
     */
    public function send(SmsMessage $message): void
    {
        // no operation
    }
}
