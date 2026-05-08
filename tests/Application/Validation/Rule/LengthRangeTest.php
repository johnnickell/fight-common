<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\LengthRange;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LengthRange::class)]
class LengthRangeTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_length_is_within_range(): void
    {
        self::assertTrue((new LengthRange(3, 10))->isSatisfiedBy('hello'));
    }

    public function test_that_is_satisfied_by_returns_false_when_length_is_outside_range(): void
    {
        self::assertFalse((new LengthRange(6, 10))->isSatisfiedBy('hello'));
    }
}
