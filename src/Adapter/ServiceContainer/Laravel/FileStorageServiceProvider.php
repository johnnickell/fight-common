<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\FileStorage\FlysystemStorage;
use Fight\Common\Application\FileStorage\FileStorage;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Class FileStorageServiceProvider
 *
 * Registers the selected Laravel disk through the complete Flysystem storage adapter.
 *
 * The consuming application owns the selected disk and all disk policy.
 */
final class FileStorageServiceProvider extends ServiceProvider
{
    /**
     * Registers the selected disk-backed file storage capability
     */
    public function register(): void
    {
        $this->app->singleton(FileStorage::class, static function (Container $container): FlysystemStorage {
            $config = $container->make('config');
            assert($config instanceof Config);
            $disk = $config->get('fight-common.file-storage.disk');

            if (!is_string($disk) || $disk === '') {
                throw new InvalidArgumentException(
                    'The fight-common.file-storage.disk configuration must name a Laravel disk.'
                );
            }

            $filesystem = $container->make(FilesystemFactory::class)->disk($disk);
            assert($filesystem instanceof FilesystemAdapter);

            $driver = $filesystem->getDriver();

            return new FlysystemStorage($driver);
        });
    }
}
