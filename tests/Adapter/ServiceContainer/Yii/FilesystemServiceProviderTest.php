<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Adapter\ServiceContainer\Yii\FilesystemServiceProvider;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FilesystemServiceProvider::class)]
final class FilesystemServiceProviderTest extends UnitTestCase
{
    public function test_that_get_definitions_binds_the_symfony_filesystem_fallback(): void
    {
        self::assertSame(
            [Filesystem::class => ['class' => SymfonyFilesystem::class]],
            (new FilesystemServiceProvider())->getDefinitions()
        );
    }

    public function test_that_get_extensions_returns_no_extensions(): void
    {
        self::assertSame([], (new FilesystemServiceProvider())->getExtensions());
    }
}
