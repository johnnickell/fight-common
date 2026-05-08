<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsEmpty;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsEmpty::class)]
class IsEmptyTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_empty_string(): void
    {
        self::assertTrue((new IsEmpty())->isSatisfiedBy(''));
    }

    public function test_that_is_satisfied_by_returns_true_for_empty_array(): void
    {
        self::assertTrue((new IsEmpty())->isSatisfiedBy([]));
    }

    public function test_that_is_satisfied_by_returns_true_for_null(): void
    {
        self::assertTrue((new IsEmpty())->isSatisfiedBy(null));
    }

    public function test_that_is_satisfied_by_returns_false_for_non_empty_string(): void
    {
        self::assertFalse((new IsEmpty())->isSatisfiedBy('hello'));
    }
}
