<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Scheduler\Exception;

use Fight\Common\Application\Scheduler\Exception\LockException;
use Fight\Common\Application\Scheduler\Exception\SchedulerException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LockException::class)]
class LockExceptionTest extends UnitTestCase
{
    public function test_that_lock_exception_extends_scheduler_exception(): void
    {
        $exception = new LockException('locked');

        self::assertInstanceOf(SchedulerException::class, $exception);
    }
}
