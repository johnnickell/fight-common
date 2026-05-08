<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\NumberExact;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NumberExact::class)]
class NumberExactTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_number_matches(): void
    {
        self::assertTrue((new NumberExact(42))->isSatisfiedBy(42));
    }

    public function test_that_is_satisfied_by_returns_false_when_number_does_not_match(): void
    {
        self::assertFalse((new NumberExact(42))->isSatisfiedBy(99));
    }
}
