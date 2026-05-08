<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsFalsy;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsFalsy::class)]
class IsFalsyTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_integer_zero(): void
    {
        self::assertTrue(new IsFalsy()->isSatisfiedBy(0));
    }

    public function test_that_is_satisfied_by_returns_true_for_empty_string(): void
    {
        self::assertTrue(new IsFalsy()->isSatisfiedBy(''));
    }

    public function test_that_is_satisfied_by_returns_true_for_boolean_false(): void
    {
        self::assertTrue(new IsFalsy()->isSatisfiedBy(false));
    }

    public function test_that_is_satisfied_by_returns_false_for_integer_one(): void
    {
        self::assertFalse(new IsFalsy()->isSatisfiedBy(1));
    }
}
