<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\FileStorage\FlysystemStorage;
use Fight\Common\Adapter\ServiceContainer\Laravel\FileStorageServiceProvider;
use Fight\Common\Application\Cache\Cache;
use Fight\Common\Application\FileStorage\FileStorage;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use League\Flysystem\FilesystemOperator;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FileStorageServiceProvider::class)]
final class FileStorageServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_file_storage_provider_binds_only_the_selected_disk_through_the_complete_flysystem_adapter(): void
    {
        $application = new Application(__DIR__);
        $application->instance('config', new Repository([
            'fight-common' => ['file-storage' => ['disk' => 'testing-disk']]
        ]));

        /** @var MockInterface&FilesystemFactory $factory */
        $factory = $this->mock(FilesystemFactory::class);
        /** @var MockInterface&FilesystemAdapter $disk */
        $disk = $this->mock(FilesystemAdapter::class);
        /** @var MockInterface&FilesystemOperator $driver */
        $driver = $this->mock(FilesystemOperator::class);
        $factory->shouldReceive('disk')->once()->with('testing-disk')->andReturn($disk);
        $disk->shouldReceive('getDriver')->once()->andReturn($driver);
        $application->instance(FilesystemFactory::class, $factory);
        $application->register(FileStorageServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(FileStorage::class));
        self::assertFalse($application->bound(Cache::class));
        self::assertFalse($application->bound(Filesystem::class));
        self::assertFalse($application->bound(MailTransport::class));
        self::assertInstanceOf(FlysystemStorage::class, $application->make(FileStorage::class));
    }

    public function test_that_file_storage_provider_rejects_a_missing_selected_disk(): void
    {
        $application = new Application(__DIR__);
        $application->instance('config', new Repository());
        $application->register(FileStorageServiceProvider::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The fight-common.file-storage.disk configuration must name a Laravel disk.'
        );

        $application->make(FileStorage::class);
    }
}
