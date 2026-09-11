<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Http\Laravel\JSendResponse;
use Illuminate\Support\ServiceProvider;

/**
 * Class HttpServiceProvider
 */
final class HttpServiceProvider extends ServiceProvider
{
    /**
     * Registers the HTTP response capability
     */
    public function register(): void
    {
        $this->app->singleton(JSendResponse::class);
    }
}
