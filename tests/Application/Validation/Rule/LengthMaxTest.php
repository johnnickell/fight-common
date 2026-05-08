<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\LengthMax;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LengthMax::class)]
class LengthMaxTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_length_is_within_maximum(): void
    {
        self::assertTrue((new LengthMax(10))->isSatisfiedBy('hello'));
    }

    public function test_that_is_satisfied_by_returns_false_when_length_exceeds_maximum(): void
    {
        self::assertFalse((new LengthMax(3))->isSatisfiedBy('hello'));
    }
}
