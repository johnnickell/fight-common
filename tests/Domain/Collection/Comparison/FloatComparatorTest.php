<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Comparison;

use AssertionError;
use Fight\Common\Domain\Collection\Comparison\FloatComparator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FloatComparator::class)]
class FloatComparatorTest extends UnitTestCase
{
    public function test_that_compare_returns_zero_for_equal_floats(): void
    {
        $comparator = new FloatComparator();

        self::assertSame(0, $comparator->compare(3.14, 3.14));
    }

    public function test_that_compare_returns_negative_when_first_is_lesser(): void
    {
        $comparator = new FloatComparator();

        self::assertLessThan(0, $comparator->compare(1.5, 2.5));
    }

    public function test_that_compare_returns_positive_when_first_is_greater(): void
    {
        $comparator = new FloatComparator();

        self::assertGreaterThan(0, $comparator->compare(2.5, 1.5));
    }

    public function test_that_compare_throws_for_non_float_first_argument(): void
    {
        $comparator = new FloatComparator();

        $this->expectException(AssertionError::class);
        $comparator->compare('not-a-float', 1.0);
    }

    public function test_that_compare_throws_for_non_float_second_argument(): void
    {
        $comparator = new FloatComparator();

        $this->expectException(AssertionError::class);
        $comparator->compare(1.0, 'not-a-float');
    }
}
