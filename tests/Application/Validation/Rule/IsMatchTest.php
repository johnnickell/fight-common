<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsMatch;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsMatch::class)]
class IsMatchTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_candidate_matches_pattern(): void
    {
        self::assertTrue((new IsMatch('/^\d{3}-\d{4}$/'))->isSatisfiedBy('555-1234'));
    }

    public function test_that_is_satisfied_by_returns_false_when_candidate_does_not_match_pattern(): void
    {
        self::assertFalse((new IsMatch('/^\d{3}-\d{4}$/'))->isSatisfiedBy('hello'));
    }
}
