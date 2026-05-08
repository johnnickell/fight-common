<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsAlphaDashed;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsAlphaDashed::class)]
class IsAlphaDashedTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_hyphenated_alpha_string(): void
    {
        self::assertTrue(new IsAlphaDashed()->isSatisfiedBy('hello-world'));
    }

    public function test_that_is_satisfied_by_returns_false_for_string_with_spaces(): void
    {
        self::assertFalse(new IsAlphaDashed()->isSatisfiedBy('hello world'));
    }
}
