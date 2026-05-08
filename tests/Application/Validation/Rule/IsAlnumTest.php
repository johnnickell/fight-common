<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsAlnum;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsAlnum::class)]
class IsAlnumTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_alphanumeric_string(): void
    {
        self::assertTrue((new IsAlnum())->isSatisfiedBy('hello1'));
    }

    public function test_that_is_satisfied_by_returns_false_for_string_with_special_characters(): void
    {
        self::assertFalse((new IsAlnum())->isSatisfiedBy('hello!'));
    }
}
