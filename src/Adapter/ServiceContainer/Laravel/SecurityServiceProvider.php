<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Auth\Security\Laravel\LaravelPasswordHasher;
use Fight\Common\Adapter\Auth\Security\Laravel\LaravelPasswordValidator;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\ServiceProvider;

/**
 * Class SecurityServiceProvider
 */
final class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Registers the password security capability
     */
    public function register(): void
    {
        $this->app->singleton(
            PasswordHasher::class,
            static function (Container $container): LaravelPasswordHasher {
                $hasher = $container->make(Hasher::class);

                return new LaravelPasswordHasher($hasher);
            }
        );
        $this->app->singleton(
            PasswordValidator::class,
            static function (Container $container): LaravelPasswordValidator {
                $hasher = $container->make(Hasher::class);

                return new LaravelPasswordValidator($hasher);
            }
        );
    }
}
