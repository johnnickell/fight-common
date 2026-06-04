<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Process;

use Fight\Common\Application\Process\ProcessErrorBehavior;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProcessErrorBehavior::class)]
class ProcessErrorBehaviorTest extends UnitTestCase
{
    public function test_that_all_cases_have_correct_values(): void
    {
        self::assertSame(1, ProcessErrorBehavior::EXCEPTION->value);
        self::assertSame(2, ProcessErrorBehavior::IGNORE->value);
        self::assertSame(3, ProcessErrorBehavior::RETRY->value);
    }

    public function test_that_from_returns_correct_case(): void
    {
        self::assertSame(ProcessErrorBehavior::EXCEPTION, ProcessErrorBehavior::from(1));
        self::assertSame(ProcessErrorBehavior::IGNORE, ProcessErrorBehavior::from(2));
        self::assertSame(ProcessErrorBehavior::RETRY, ProcessErrorBehavior::from(3));
    }
}
