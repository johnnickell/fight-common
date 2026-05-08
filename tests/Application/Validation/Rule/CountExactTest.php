<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\CountExact;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CountExact::class)]
class CountExactTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_count_matches(): void
    {
        self::assertTrue((new CountExact(3))->isSatisfiedBy(['a', 'b', 'c']));
    }

    public function test_that_is_satisfied_by_returns_false_when_count_does_not_match(): void
    {
        self::assertFalse((new CountExact(3))->isSatisfiedBy(['a', 'b']));
    }
}
