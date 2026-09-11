<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\Messaging\Laravel\LaravelCommandBus;
use Fight\Common\Adapter\Messaging\Laravel\LaravelEventDispatcher;
use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\AsynchronousEventDispatcher;
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
        $this->app->singleton(AsynchronousCommandBus::class, LaravelCommandBus::class);
        $this->app->singleton(AsynchronousEventDispatcher::class, LaravelEventDispatcher::class);
    }
}
