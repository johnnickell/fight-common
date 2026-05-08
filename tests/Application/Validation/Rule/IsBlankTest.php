<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsBlank;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsBlank::class)]
class IsBlankTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_empty_string(): void
    {
        self::assertTrue(new IsBlank()->isSatisfiedBy(''));
    }

    public function test_that_is_satisfied_by_returns_true_for_whitespace_only_string(): void
    {
        self::assertTrue(new IsBlank()->isSatisfiedBy('   '));
    }

    public function test_that_is_satisfied_by_returns_false_for_non_blank_string(): void
    {
        self::assertFalse(new IsBlank()->isSatisfiedBy('hello'));
    }
}
