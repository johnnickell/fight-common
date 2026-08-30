<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Filesystem\Laravel;

use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Records the Laravel 13.29.0 prototype gaps that prevent a complete Fight adapter.
 */
#[CoversNothing]
final class LaravelFilesystemPrototypeCapabilityTest extends UnitTestCase
{
    public function test_that_native_filesystem_does_not_offer_required_fight_operations(): void
    {
        $filesystem = new Filesystem();

        self::assertFalse(method_exists($filesystem, 'touch'));
        self::assertFalse(method_exists($filesystem, 'lastAccessed'));
        self::assertFalse(method_exists($filesystem, 'isExecutable'));
        self::assertFalse(method_exists($filesystem, 'chown'));
        self::assertFalse(method_exists($filesystem, 'chgrp'));
    }
}
