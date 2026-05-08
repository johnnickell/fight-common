<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\CountRange;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CountRange::class)]
class CountRangeTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_count_is_within_range(): void
    {
        self::assertTrue(new CountRange(2, 5)->isSatisfiedBy(['a', 'b', 'c']));
    }

    public function test_that_is_satisfied_by_returns_false_when_count_is_outside_range(): void
    {
        self::assertFalse(new CountRange(4, 10)->isSatisfiedBy(['a', 'b', 'c']));
    }
}
