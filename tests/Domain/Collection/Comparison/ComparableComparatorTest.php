<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Comparison;

use AssertionError;
use Fight\Common\Domain\Collection\Comparison\ComparableComparator;
use Fight\Common\Domain\Type\Comparable;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ComparableComparator::class)]
class ComparableComparatorTest extends UnitTestCase
{
    private function makeComparable(int $value): Comparable
    {
        return new class ($value) implements Comparable {
            public function __construct(private readonly int $value) {}

            public function compareTo(mixed $other): int
            {
                return $this->value <=> $other->value;
            }
        };
    }

    public function test_that_compare_returns_zero_when_objects_are_equal(): void
    {
        $comparator = new ComparableComparator();
        $a = $this->makeComparable(5);
        $b = $this->makeComparable(5);

        self::assertSame(0, $comparator->compare($a, $b));
    }

    public function test_that_compare_returns_negative_when_first_is_lesser(): void
    {
        $comparator = new ComparableComparator();
        $a = $this->makeComparable(3);
        $b = $this->makeComparable(7);

        self::assertLessThan(0, $comparator->compare($a, $b));
    }

    public function test_that_compare_returns_positive_when_first_is_greater(): void
    {
        $comparator = new ComparableComparator();
        $a = $this->makeComparable(7);
        $b = $this->makeComparable(3);

        self::assertGreaterThan(0, $comparator->compare($a, $b));
    }

    public function test_that_compare_throws_for_non_comparable_first_argument(): void
    {
        $comparator = new ComparableComparator();

        $this->expectException(AssertionError::class);
        $comparator->compare(new \stdClass(), $this->makeComparable(5));
    }

    public function test_that_compare_throws_for_mismatched_types(): void
    {
        $comparator = new ComparableComparator();

        $a = $this->makeComparable(5);
        $b = new class (5) implements Comparable {
            public function __construct(private readonly int $value) {}

            public function compareTo(mixed $other): int
            {
                return $this->value <=> $other->value;
            }
        };

        $this->expectException(AssertionError::class);
        $comparator->compare($a, $b);
    }
}
