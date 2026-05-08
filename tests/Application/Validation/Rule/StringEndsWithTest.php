<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\StringEndsWith;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StringEndsWith::class)]
class StringEndsWithTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_string_ends_with_suffix(): void
    {
        self::assertTrue((new StringEndsWith('world'))->isSatisfiedBy('hello world'));
    }

    public function test_that_is_satisfied_by_returns_false_when_string_does_not_end_with_suffix(): void
    {
        self::assertFalse((new StringEndsWith('hello'))->isSatisfiedBy('hello world'));
    }
}
