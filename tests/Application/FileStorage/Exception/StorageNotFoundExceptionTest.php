<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\FileStorage\Exception;

use Fight\Common\Application\FileStorage\Exception\FileStorageException;
use Fight\Common\Application\FileStorage\Exception\StorageNotFoundException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StorageNotFoundException::class)]
class StorageNotFoundExceptionTest extends UnitTestCase
{
    public function test_that_construction_with_message_and_key_sets_both(): void
    {
        $exception = new StorageNotFoundException('Storage not found: s3', 's3');

        self::assertSame('Storage not found: s3', $exception->getMessage());
        self::assertSame('s3', $exception->getKey());
    }

    public function test_that_from_key_creates_exception_with_formatted_message_and_correct_key(): void
    {
        $exception = StorageNotFoundException::fromKey('cdn');

        self::assertSame('cdn', $exception->getKey());
        self::assertStringContainsString('cdn', $exception->getMessage());
    }

    public function test_that_get_key_returns_null_when_no_key_provided(): void
    {
        $exception = new StorageNotFoundException('Storage not found');

        self::assertNull($exception->getKey());
    }

    public function test_that_storage_not_found_exception_extends_file_storage_exception(): void
    {
        $exception = new StorageNotFoundException('Storage not found: s3', 's3');

        self::assertInstanceOf(FileStorageException::class, $exception);
    }
}
