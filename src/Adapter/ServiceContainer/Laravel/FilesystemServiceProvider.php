<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Filesystem\Laravel\LaravelFilesystem;
use Fight\Common\Application\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem as IlluminateFilesystem;
use Illuminate\Support\ServiceProvider;

/**
 * Class FilesystemServiceProvider
 *
 * Registers the complete Laravel local-filesystem adapter.
 */
final class FilesystemServiceProvider extends ServiceProvider
{
    /**
     * Registers the complete local-filesystem adapter without application path policy
     */
    public function register(): void
    {
        $this->app->singleton(
            Filesystem::class,
            static function (Application $application): LaravelFilesystem {
                /** @var IlluminateFilesystem $native */
                $native = $application->bound('files') ? $application->make('files') : new IlluminateFilesystem();

                return new LaravelFilesystem($native);
            }
        );
    }
}
