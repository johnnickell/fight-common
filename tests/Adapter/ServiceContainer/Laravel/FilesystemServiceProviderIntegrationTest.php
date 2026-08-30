<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Adapter\ServiceContainer\Laravel\FilesystemServiceProvider;
use Fight\Common\Application\Cache\Cache;
use Fight\Common\Application\FileStorage\FileStorage;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FilesystemServiceProvider::class)]
final class FilesystemServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_filesystem_provider_binds_only_the_complete_symfony_fallback_in_a_booted_laravel_application(): void
    {
        $application = new Application(__DIR__);
        $application->register(FilesystemServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(Filesystem::class));
        self::assertFalse($application->bound(Cache::class));
        self::assertFalse($application->bound(FileStorage::class));
        self::assertFalse($application->bound(MailTransport::class));
        self::assertInstanceOf(SymfonyFilesystem::class, $application->make(Filesystem::class));
    }
}
