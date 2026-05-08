<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\NumberMax;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NumberMax::class)]
class NumberMaxTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_number_is_within_maximum(): void
    {
        self::assertTrue((new NumberMax(100))->isSatisfiedBy(42));
    }

    public function test_that_is_satisfied_by_returns_false_when_number_exceeds_maximum(): void
    {
        self::assertFalse((new NumberMax(10))->isSatisfiedBy(42));
    }
}
