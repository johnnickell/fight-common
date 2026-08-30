<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mail\Laravel;

use Fight\Common\Application\Mail\Exception\MailException;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Illuminate\Contracts\Mail\Mailer;
use Throwable;

/**
 * Class LaravelMailTransport
 *
 * Delivers Fight messages through Laravel's selected native mailer.
 */
final readonly class LaravelMailTransport implements MailTransport
{
    /**
     * Constructs LaravelMailTransport
     */
    public function __construct(private Mailer $mailer)
    {
    }

    /**
     * Sends a Fight mail message
     */
    public function send(MailMessage $message): void
    {
        try {
            $this->mailer->send(new FightMailMailable($message));
        } catch (Throwable $throwable) {
            throw new MailException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }
}
