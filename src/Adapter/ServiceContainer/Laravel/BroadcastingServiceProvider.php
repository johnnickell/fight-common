<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Socket\Laravel\LaravelBroadcastPublisher;
use Fight\Common\Application\Socket\Publisher;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Class BroadcastingServiceProvider
 *
 * Registers the selected Laravel broadcasting capability.
 */
final class BroadcastingServiceProvider extends ServiceProvider
{
    /**
     * Registers the broadcasting capability
     */
    public function register(): void
    {
        $this->app->singleton(Publisher::class, static function (Container $container): LaravelBroadcastPublisher {
            $factory = $container->make(Factory::class);
            $broadcaster = $factory->connection();
            $eventName = $container->make('config')->get('fight.broadcast.event_name');
            assert(is_string($eventName));

            return new LaravelBroadcastPublisher($broadcaster, $eventName);
        });
    }
}
