<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsAlpha;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsAlpha::class)]
class IsAlphaTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_alphabetic_string(): void
    {
        self::assertTrue(new IsAlpha()->isSatisfiedBy('hello'));
    }

    public function test_that_is_satisfied_by_returns_false_for_alphanumeric_string(): void
    {
        self::assertFalse(new IsAlpha()->isSatisfiedBy('hello1'));
    }
}
