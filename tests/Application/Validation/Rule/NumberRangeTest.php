<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\NumberRange;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NumberRange::class)]
class NumberRangeTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_number_is_within_range(): void
    {
        self::assertTrue((new NumberRange(1, 100))->isSatisfiedBy(42));
    }

    public function test_that_is_satisfied_by_returns_false_when_number_is_outside_range(): void
    {
        self::assertFalse((new NumberRange(50, 100))->isSatisfiedBy(42));
    }
}
