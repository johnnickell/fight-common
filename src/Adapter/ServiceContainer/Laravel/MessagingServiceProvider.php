<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Illuminate\Support\ServiceProvider;

/**
 * Class MessagingServiceProvider
 */
final class MessagingServiceProvider extends ServiceProvider
{
    /**
     * Registers the messaging capability
     */
    public function register(): void
    {
        $this->app->singleton(CommandMessageHandler::class);
        $this->app->singleton(EventMessageHandler::class);
    }
}
