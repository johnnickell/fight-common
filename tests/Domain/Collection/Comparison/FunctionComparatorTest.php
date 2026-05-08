<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Comparison;

use Fight\Common\Domain\Collection\Comparison\FunctionComparator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FunctionComparator::class)]
class FunctionComparatorTest extends UnitTestCase
{
    public function test_that_compare_returns_zero_when_callback_returns_zero(): void
    {
        $comparator = new FunctionComparator(fn($a, $b) => 0);

        self::assertSame(0, $comparator->compare('x', 'x'));
    }

    public function test_that_compare_returns_negative_when_callback_returns_negative(): void
    {
        $comparator = new FunctionComparator(fn($a, $b) => -1);

        self::assertSame(-1, $comparator->compare('a', 'b'));
    }

    public function test_that_compare_returns_positive_when_callback_returns_positive(): void
    {
        $comparator = new FunctionComparator(fn($a, $b) => 1);

        self::assertSame(1, $comparator->compare('b', 'a'));
    }

    public function test_that_compare_passes_arguments_to_callback(): void
    {
        $received = [];
        $comparator = new FunctionComparator(function ($a, $b) use (&$received) {
            $received = [$a, $b];
            return 0;
        });

        $comparator->compare('first', 'second');

        self::assertSame(['first', 'second'], $received);
    }
}
