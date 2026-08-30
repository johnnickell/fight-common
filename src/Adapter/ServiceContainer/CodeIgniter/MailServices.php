<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\CodeIgniter;

use Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Class MailServices
 */
final class MailServices
{
    /**
     * Creates the Symfony mail message factory fallback
     */
    public static function mailFactory(): MailFactory
    {
        return new SymfonyMailFactory();
    }

    /**
     * Creates the Symfony mail transport fallback
     */
    public static function mailTransport(MailerInterface $mailer): MailTransport
    {
        return new SymfonyMailTransport($mailer);
    }
}
