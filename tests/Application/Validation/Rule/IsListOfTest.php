<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsListOf;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsListOf::class)]
class IsListOfTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_homogeneous_array(): void
    {
        self::assertTrue((new IsListOf('string'))->isSatisfiedBy(['a', 'b', 'c']));
    }

    public function test_that_is_satisfied_by_returns_false_for_mixed_type_array(): void
    {
        self::assertFalse((new IsListOf('string'))->isSatisfiedBy(['a', 1, 'c']));
    }
}
