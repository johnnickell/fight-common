<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\LengthExact;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LengthExact::class)]
class LengthExactTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_length_matches(): void
    {
        self::assertTrue(new LengthExact(5)->isSatisfiedBy('hello'));
    }

    public function test_that_is_satisfied_by_returns_false_when_length_does_not_match(): void
    {
        self::assertFalse(new LengthExact(5)->isSatisfiedBy('hi'));
    }
}
