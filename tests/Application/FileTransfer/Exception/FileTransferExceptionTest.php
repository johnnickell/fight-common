<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\FileTransfer\Exception;

use Fight\Common\Application\FileTransfer\Exception\FileTransferException;
use Fight\Common\Domain\Exception\SystemException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FileTransferException::class)]
class FileTransferExceptionTest extends UnitTestCase
{
    public function test_that_file_transfer_exception_extends_system_exception(): void
    {
        $exception = new FileTransferException('error');

        self::assertInstanceOf(SystemException::class, $exception);
    }
}
