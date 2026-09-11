<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Filesystem\Laravel\LaravelFilesystem;
use Fight\Common\Adapter\ServiceContainer\Laravel\FilesystemServiceProvider;
use Fight\Common\Application\Cache\Cache;
use Fight\Common\Application\FileStorage\FileStorage;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Filesystem\Filesystem as IlluminateFilesystem;
use Illuminate\Foundation\Application;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FilesystemServiceProvider::class)]
final class FilesystemServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_filesystem_provider_binds_only_the_complete_laravel_adapter_in_a_booted_application(): void
    {
        $application = new Application(__DIR__);
        $application->register(FilesystemServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(Filesystem::class));
        self::assertFalse($application->bound(Cache::class));
        self::assertFalse($application->bound(FileStorage::class));
        self::assertFalse($application->bound(MailTransport::class));
        self::assertInstanceOf(LaravelFilesystem::class, $application->make(Filesystem::class));
    }

    public function test_that_filesystem_provider_adapts_the_registered_files_service(): void
    {
        /** @var IlluminateFilesystem&MockInterface $native */
        $native = $this->mock(IlluminateFilesystem::class);
        $native->shouldReceive('isFile')->once()->with('/sentinel')->andReturnTrue();
        $application = new Application(__DIR__);
        $application->instance('files', $native);
        $application->register(FilesystemServiceProvider::class);

        self::assertTrue($application->make(Filesystem::class)->isFile('/sentinel'));
    }
}
