<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Process\Exception;

use Fight\Common\Application\Process\Exception\ProcessException;
use Fight\Common\Application\Process\Exception\ProcessFailedException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProcessFailedException::class)]
class ProcessFailedExceptionTest extends UnitTestCase
{
    public function test_that_process_failed_exception_extends_process_exception(): void
    {
        $exception = new ProcessFailedException('process failed');

        self::assertInstanceOf(ProcessException::class, $exception);
    }
}
