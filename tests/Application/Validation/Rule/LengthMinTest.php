<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\LengthMin;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LengthMin::class)]
class LengthMinTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_length_meets_minimum(): void
    {
        self::assertTrue((new LengthMin(3))->isSatisfiedBy('hello'));
    }

    public function test_that_is_satisfied_by_returns_false_when_length_is_below_minimum(): void
    {
        self::assertFalse((new LengthMin(5))->isSatisfiedBy('hi'));
    }
}
