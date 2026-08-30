<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Routing\Laravel\LaravelUrlGenerator;
use Fight\Common\Application\Routing\UrlGenerator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator as NativeUrlGenerator;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Class RoutingServiceProvider
 *
 * Registers the routing capability.
 */
final class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Registers the URL generator port
     */
    public function register(): void
    {
        $this->app->singleton(
            UrlGenerator::class,
            static function (Container $container): LaravelUrlGenerator {
                $urlGenerator = $container->make('url');
                assert($urlGenerator instanceof NativeUrlGenerator);

                $router = $container->make('router');
                assert($router instanceof Router);

                return new LaravelUrlGenerator($urlGenerator, $router->getRoutes());
            }
        );
    }
}
