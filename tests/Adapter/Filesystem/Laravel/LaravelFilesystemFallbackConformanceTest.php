<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Filesystem\Laravel;

use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Test\Common\TestCase\Filesystem\FilesystemConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The selected Laravel filesystem fallback must retain the complete Fight contract.
 */
#[CoversClass(SymfonyFilesystem::class)]
final class LaravelFilesystemFallbackConformanceTest extends FilesystemConformanceTestCase
{
    protected function create_filesystem(): Filesystem
    {
        return new SymfonyFilesystem();
    }
}
