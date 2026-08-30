<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Filesystem\CodeIgniter;

use CodeIgniter\Files\File;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Records the native file-object gaps that require the complete Symfony Filesystem fallback. */
#[CoversNothing]
final class CodeIgniterFilesystemPrototypeCapabilityTest extends UnitTestCase
{
    public function test_that_native_file_object_does_not_offer_required_fight_filesystem_operations(): void
    {
        self::assertFalse(method_exists(File::class, 'touch'));
        self::assertFalse(method_exists(File::class, 'mirror'));
        self::assertFalse(method_exists(File::class, 'symlink'));
    }
}
