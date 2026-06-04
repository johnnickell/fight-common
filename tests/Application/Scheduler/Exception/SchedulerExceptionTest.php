<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Scheduler\Exception;

use Fight\Common\Application\Scheduler\Exception\SchedulerException;
use Fight\Common\Domain\Exception\SystemException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SchedulerException::class)]
class SchedulerExceptionTest extends UnitTestCase
{
    public function test_that_scheduler_exception_extends_system_exception(): void
    {
        $exception = new SchedulerException('error');

        self::assertInstanceOf(SystemException::class, $exception);
    }
}
