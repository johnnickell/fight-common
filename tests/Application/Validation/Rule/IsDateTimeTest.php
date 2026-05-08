<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsDateTime;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsDateTime::class)]
class IsDateTimeTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_valid_date_matching_format(): void
    {
        self::assertTrue(new IsDateTime('Y-m-d')->isSatisfiedBy('2024-01-15'));
    }

    public function test_that_is_satisfied_by_returns_false_for_invalid_date_string(): void
    {
        self::assertFalse(new IsDateTime('Y-m-d')->isSatisfiedBy('not-a-date'));
    }

    public function test_that_is_satisfied_by_returns_false_for_non_string_candidate(): void
    {
        self::assertFalse(new IsDateTime('Y-m-d')->isSatisfiedBy(null));
    }
}
