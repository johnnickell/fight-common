<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mail\Null;

use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;

/**
 * Class NullMailTransport
 */
final class NullMailTransport implements MailTransport
{
    /**
     * @inheritDoc
     */
    public function send(MailMessage $message): void
    {
        // no operation
    }
}
