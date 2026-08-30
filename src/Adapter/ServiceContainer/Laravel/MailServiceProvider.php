<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Mail\Laravel\LaravelMailFactory;
use Fight\Common\Adapter\Mail\Laravel\LaravelMailTransport;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\ServiceProvider;

/**
 * Class MailServiceProvider
 *
 * Registers the selected Laravel mail capability.
 */
final class MailServiceProvider extends ServiceProvider
{
    /**
     * Registers the mail capability
     */
    public function register(): void
    {
        $this->app->singleton(MailFactory::class, LaravelMailFactory::class);
        $this->app->singleton(MailTransport::class, static function (Container $container): LaravelMailTransport {
            $mailer = $container->make('mailer');
            assert($mailer instanceof Mailer);

            return new LaravelMailTransport($mailer);
        });
    }
}
