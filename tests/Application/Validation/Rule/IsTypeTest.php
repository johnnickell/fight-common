<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsType;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(IsType::class)]
class IsTypeTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_matching_type(): void
    {
        self::assertTrue((new IsType('string'))->isSatisfiedBy('hello'));
    }

    public function test_that_is_satisfied_by_returns_false_for_non_matching_type(): void
    {
        self::assertFalse((new IsType('string'))->isSatisfiedBy(42));
    }

    public function test_that_is_satisfied_by_returns_true_for_nullable_type_with_null(): void
    {
        self::assertTrue((new IsType('?string'))->isSatisfiedBy(null));
    }
}
