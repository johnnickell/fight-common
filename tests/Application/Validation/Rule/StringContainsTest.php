<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\StringContains;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StringContains::class)]
class StringContainsTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_string_contains_substring(): void
    {
        self::assertTrue((new StringContains('world'))->isSatisfiedBy('hello world'));
    }

    public function test_that_is_satisfied_by_returns_false_when_string_does_not_contain_substring(): void
    {
        self::assertFalse((new StringContains('world'))->isSatisfiedBy('hello there'));
    }
}
