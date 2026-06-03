<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Process\Exception;

use Fight\Common\Application\Process\Exception\ProcessException;
use Fight\Common\Domain\Exception\SystemException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProcessException::class)]
class ProcessExceptionTest extends UnitTestCase
{
    public function test_that_process_exception_extends_system_exception(): void
    {
        $exception = new ProcessException('error');

        self::assertInstanceOf(SystemException::class, $exception);
    }
}
