<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Comparison;

use AssertionError;
use Fight\Common\Domain\Collection\Comparison\StringComparator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StringComparator::class)]
class StringComparatorTest extends UnitTestCase
{
    public function test_that_compare_returns_zero_for_equal_strings(): void
    {
        $comparator = new StringComparator();

        self::assertSame(0, $comparator->compare('apple', 'apple'));
    }

    public function test_that_compare_returns_negative_when_first_is_lesser(): void
    {
        $comparator = new StringComparator();

        self::assertLessThan(0, $comparator->compare('apple', 'banana'));
    }

    public function test_that_compare_returns_positive_when_first_is_greater(): void
    {
        $comparator = new StringComparator();

        self::assertGreaterThan(0, $comparator->compare('banana', 'apple'));
    }

    public function test_that_compare_throws_for_non_string_first_argument(): void
    {
        $comparator = new StringComparator();

        $this->expectException(AssertionError::class);
        $comparator->compare(42, 'apple');
    }

    public function test_that_compare_throws_for_non_string_second_argument(): void
    {
        $comparator = new StringComparator();

        $this->expectException(AssertionError::class);
        $comparator->compare('apple', 42);
    }
}
