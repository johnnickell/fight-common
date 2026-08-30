<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Symfony\Component\Mailer\MailerInterface;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\ServiceProviderInterface;

/**
 * Class MailServiceProvider
 *
 * Registers the proven Symfony Mail fallback for a selected Yii mail capability.
 */
final class MailServiceProvider implements ServiceProviderInterface
{
    /**
     * Returns mail definitions without boot side effects
     *
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            MailFactory::class   => ['class' => SymfonyMailFactory::class],
            MailTransport::class => [
                'class'         => SymfonyMailTransport::class,
                '__construct()' => [Reference::to(MailerInterface::class)]
            ]
        ];
    }

    /**
     * Returns no mail extensions
     *
     * @return array<string, callable>
     */
    public function getExtensions(): array
    {
        return [];
    }
}
