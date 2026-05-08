<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use DateTimeZone;
use Fight\Common\Application\Validation\Rule\IsTimezone;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsTimezone::class)]
class IsTimezoneTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_valid_timezone(): void
    {
        self::assertTrue(new IsTimezone()->isSatisfiedBy('America/New_York'));
    }

    public function test_that_is_satisfied_by_returns_false_for_invalid_timezone(): void
    {
        self::assertFalse(new IsTimezone()->isSatisfiedBy('Mars/Olympus'));
    }

    public function test_that_is_satisfied_by_returns_true_for_date_time_zone_object(): void
    {
        self::assertTrue(new IsTimezone()->isSatisfiedBy(new DateTimeZone('UTC')));
    }
}
