<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\MailServices;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Transport\MailTransport;
use RuntimeException;
use Symfony\Component\Mailer\MailerInterface;

/** Project-owned mail-only Config\Services fixture. */
final class Services extends BaseService
{
    public static function fightMailFactory(bool $getShared = true): MailFactory
    {
        if ($getShared) {
            return static::getSharedInstance('fightMailFactory');
        }

        return MailServices::mailFactory();
    }

    public static function fightMailTransport(bool $getShared = true): MailTransport
    {
        if ($getShared) {
            return static::getSharedInstance('fightMailTransport');
        }

        return MailServices::mailTransport(static::fightMailer());
    }

    private static function fightMailer(): MailerInterface
    {
        $mailer = static::get('fightMailerCollaborator');

        if (! $mailer instanceof MailerInterface) {
            throw new RuntimeException('The project mailer collaborator is unavailable.');
        }

        return $mailer;
    }
}
