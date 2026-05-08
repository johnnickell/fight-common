<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsTruthy;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsTruthy::class)]
class IsTruthyTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_integer_one(): void
    {
        self::assertTrue(new IsTruthy()->isSatisfiedBy(1));
    }

    public function test_that_is_satisfied_by_returns_true_for_non_empty_string(): void
    {
        self::assertTrue(new IsTruthy()->isSatisfiedBy('yes'));
    }

    public function test_that_is_satisfied_by_returns_true_for_boolean_true(): void
    {
        self::assertTrue(new IsTruthy()->isSatisfiedBy(true));
    }

    public function test_that_is_satisfied_by_returns_false_for_integer_zero(): void
    {
        self::assertFalse(new IsTruthy()->isSatisfiedBy(0));
    }
}
