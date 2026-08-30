<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Cache\Laravel\LaravelCache;
use Fight\Common\Application\Cache\Cache;
use Fight\Common\Application\Cache\MutableCache;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Class CacheServiceProvider
 */
final class CacheServiceProvider extends ServiceProvider
{
    /**
     * Registers the cache capability
     */
    public function register(): void
    {
        $this->app->singleton(
            MutableCache::class,
            static function (Container $container): LaravelCache {
                $cache = $container->make('cache');
                assert($cache instanceof Repository);

                return new LaravelCache($cache);
            }
        );
        $this->app->alias(MutableCache::class, Cache::class);
    }
}
