<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Filesystem\Exception;

use Fight\Common\Application\Filesystem\Exception\FilesystemException;
use Fight\Common\Domain\Exception\SystemException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FilesystemException::class)]
class FilesystemExceptionTest extends UnitTestCase
{
    public function test_that_construction_with_message_and_path_sets_both(): void
    {
        $exception = new FilesystemException('Permission denied', '/var/data/file.txt');

        self::assertSame('Permission denied', $exception->getMessage());
        self::assertSame('/var/data/file.txt', $exception->getPath());
    }

    public function test_that_get_path_returns_null_when_no_path_provided(): void
    {
        $exception = new FilesystemException('An error occurred');

        self::assertNull($exception->getPath());
    }

    public function test_that_filesystem_exception_extends_system_exception(): void
    {
        $exception = new FilesystemException('Error');

        self::assertInstanceOf(SystemException::class, $exception);
    }
}
