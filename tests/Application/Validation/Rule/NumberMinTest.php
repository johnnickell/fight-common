<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\NumberMin;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NumberMin::class)]
class NumberMinTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_number_meets_minimum(): void
    {
        self::assertTrue(new NumberMin(10)->isSatisfiedBy(42));
    }

    public function test_that_is_satisfied_by_returns_false_when_number_is_below_minimum(): void
    {
        self::assertFalse(new NumberMin(100)->isSatisfiedBy(42));
    }
}
