<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\StringStartsWith;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StringStartsWith::class)]
class StringStartsWithTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_string_starts_with_prefix(): void
    {
        self::assertTrue(new StringStartsWith('hello')->isSatisfiedBy('hello world'));
    }

    public function test_that_is_satisfied_by_returns_false_when_string_does_not_start_with_prefix(): void
    {
        self::assertFalse(new StringStartsWith('world')->isSatisfiedBy('hello world'));
    }
}
