<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Comparison;

use AssertionError;
use Fight\Common\Domain\Collection\Comparison\IntegerComparator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IntegerComparator::class)]
class IntegerComparatorTest extends UnitTestCase
{
    public function test_that_compare_returns_zero_for_equal_integers(): void
    {
        $comparator = new IntegerComparator();

        self::assertSame(0, $comparator->compare(5, 5));
    }

    public function test_that_compare_returns_negative_when_first_is_lesser(): void
    {
        $comparator = new IntegerComparator();

        self::assertLessThan(0, $comparator->compare(3, 10));
    }

    public function test_that_compare_returns_positive_when_first_is_greater(): void
    {
        $comparator = new IntegerComparator();

        self::assertGreaterThan(0, $comparator->compare(10, 3));
    }

    public function test_that_compare_throws_for_non_integer_first_argument(): void
    {
        $comparator = new IntegerComparator();

        $this->expectException(AssertionError::class);
        $comparator->compare('not-an-int', 5);
    }

    public function test_that_compare_throws_for_non_integer_second_argument(): void
    {
        $comparator = new IntegerComparator();

        $this->expectException(AssertionError::class);
        $comparator->compare(5, 'not-an-int');
    }
}
