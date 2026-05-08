<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\CountMax;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CountMax::class)]
class CountMaxTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_count_is_within_maximum(): void
    {
        self::assertTrue((new CountMax(5))->isSatisfiedBy(['a', 'b', 'c']));
    }

    public function test_that_is_satisfied_by_returns_false_when_count_exceeds_maximum(): void
    {
        self::assertFalse((new CountMax(2))->isSatisfiedBy(['a', 'b', 'c']));
    }
}
