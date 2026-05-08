<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Filesystem\Exception;

use Fight\Common\Application\Filesystem\Exception\FileNotFoundException;
use Fight\Common\Application\Filesystem\Exception\FilesystemException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FileNotFoundException::class)]
class FileNotFoundExceptionTest extends UnitTestCase
{
    public function test_that_from_path_creates_instance_with_formatted_message_and_path(): void
    {
        $exception = FileNotFoundException::fromPath('/var/data/missing.txt');

        self::assertInstanceOf(FileNotFoundException::class, $exception);
        self::assertStringContainsString('/var/data/missing.txt', $exception->getMessage());
        self::assertSame('/var/data/missing.txt', $exception->getPath());
    }

    public function test_that_file_not_found_exception_extends_filesystem_exception(): void
    {
        $exception = FileNotFoundException::fromPath('/some/path');

        self::assertInstanceOf(FilesystemException::class, $exception);
    }
}
