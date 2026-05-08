<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsNull;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsNull::class)]
class IsNullTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_null(): void
    {
        self::assertTrue(new IsNull()->isSatisfiedBy(null));
    }

    public function test_that_is_satisfied_by_returns_false_for_empty_string(): void
    {
        self::assertFalse(new IsNull()->isSatisfiedBy(''));
    }

    public function test_that_is_satisfied_by_returns_false_for_integer_zero(): void
    {
        self::assertFalse(new IsNull()->isSatisfiedBy(0));
    }
}
