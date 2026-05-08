<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsDigits;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsDigits::class)]
class IsDigitsTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_digit_string(): void
    {
        self::assertTrue(new IsDigits()->isSatisfiedBy('12345'));
    }

    public function test_that_is_satisfied_by_returns_false_for_decimal_string(): void
    {
        self::assertFalse(new IsDigits()->isSatisfiedBy('123.45'));
    }
}
