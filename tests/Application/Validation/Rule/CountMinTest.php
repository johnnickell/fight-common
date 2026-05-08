<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\CountMin;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CountMin::class)]
class CountMinTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_count_meets_minimum(): void
    {
        self::assertTrue((new CountMin(2))->isSatisfiedBy(['a', 'b', 'c']));
    }

    public function test_that_is_satisfied_by_returns_false_when_count_is_below_minimum(): void
    {
        self::assertFalse((new CountMin(5))->isSatisfiedBy(['a', 'b']));
    }
}
