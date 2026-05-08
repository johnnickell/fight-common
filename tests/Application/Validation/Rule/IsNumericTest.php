<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsNumeric;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsNumeric::class)]
class IsNumericTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_decimal_string(): void
    {
        self::assertTrue(new IsNumeric()->isSatisfiedBy('123.45'));
    }

    public function test_that_is_satisfied_by_returns_true_for_negative_integer_string(): void
    {
        self::assertTrue(new IsNumeric()->isSatisfiedBy('-42'));
    }

    public function test_that_is_satisfied_by_returns_false_for_non_numeric_string(): void
    {
        self::assertFalse(new IsNumeric()->isSatisfiedBy('abc'));
    }
}
