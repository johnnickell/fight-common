<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsWholeNumber;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsWholeNumber::class)]
class IsWholeNumberTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_zero(): void
    {
        self::assertTrue((new IsWholeNumber())->isSatisfiedBy(0));
    }

    public function test_that_is_satisfied_by_returns_true_for_positive_integer(): void
    {
        self::assertTrue((new IsWholeNumber())->isSatisfiedBy(5));
    }

    public function test_that_is_satisfied_by_returns_false_for_negative_integer(): void
    {
        self::assertFalse((new IsWholeNumber())->isSatisfiedBy(-1));
    }
}
