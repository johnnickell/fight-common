<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsTrue;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsTrue::class)]
class IsTrueTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_boolean_true(): void
    {
        self::assertTrue(new IsTrue()->isSatisfiedBy(true));
    }

    public function test_that_is_satisfied_by_returns_false_for_integer_one(): void
    {
        self::assertFalse(new IsTrue()->isSatisfiedBy(1));
    }

    public function test_that_is_satisfied_by_returns_false_for_string_true(): void
    {
        self::assertFalse(new IsTrue()->isSatisfiedBy('true'));
    }
}
