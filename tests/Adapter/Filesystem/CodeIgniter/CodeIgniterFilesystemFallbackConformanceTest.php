<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Filesystem\CodeIgniter;

use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Test\Common\TestCase\Filesystem\FilesystemConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** The selected CodeIgniter filesystem fallback retains the complete Fight filesystem contract. */
#[CoversClass(SymfonyFilesystem::class)]
final class CodeIgniterFilesystemFallbackConformanceTest extends FilesystemConformanceTestCase
{
    protected function create_filesystem(): Filesystem
    {
        return new SymfonyFilesystem();
    }
}
