<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsScalar;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsScalar::class)]
class IsScalarTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_string(): void
    {
        self::assertTrue((new IsScalar())->isSatisfiedBy('hello'));
    }

    public function test_that_is_satisfied_by_returns_true_for_integer(): void
    {
        self::assertTrue((new IsScalar())->isSatisfiedBy(1));
    }

    public function test_that_is_satisfied_by_returns_true_for_boolean(): void
    {
        self::assertTrue((new IsScalar())->isSatisfiedBy(true));
    }

    public function test_that_is_satisfied_by_returns_false_for_array(): void
    {
        self::assertFalse((new IsScalar())->isSatisfiedBy([]));
    }

    public function test_that_is_satisfied_by_returns_false_for_null(): void
    {
        self::assertFalse((new IsScalar())->isSatisfiedBy(null));
    }
}
