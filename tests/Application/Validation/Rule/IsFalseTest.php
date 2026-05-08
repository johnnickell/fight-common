<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsFalse;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsFalse::class)]
class IsFalseTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_boolean_false(): void
    {
        self::assertTrue(new IsFalse()->isSatisfiedBy(false));
    }

    public function test_that_is_satisfied_by_returns_false_for_integer_zero(): void
    {
        self::assertFalse(new IsFalse()->isSatisfiedBy(0));
    }

    public function test_that_is_satisfied_by_returns_false_for_string_false(): void
    {
        self::assertFalse(new IsFalse()->isSatisfiedBy('false'));
    }
}
