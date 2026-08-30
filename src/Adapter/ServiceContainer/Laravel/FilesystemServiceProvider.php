<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Application\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

/**
 * Class FilesystemServiceProvider
 *
 * Registers the complete Symfony Filesystem fallback.
 *
 * Laravel's local filesystem prototype does not supply every Fight Filesystem operation.
 */
final class FilesystemServiceProvider extends ServiceProvider
{
    /**
     * Registers the complete local-filesystem fallback without application path policy
     */
    public function register(): void
    {
        $this->app->singleton(Filesystem::class, SymfonyFilesystem::class);
    }
}
