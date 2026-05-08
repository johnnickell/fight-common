<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Specification;

use stdClass;
use Fight\Common\Domain\Specification\AndSpecification;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AndSpecification::class)]
class AndSpecificationTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_both_specifications_are_satisfied(): void
    {
        $first = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return true; }
        };
        $second = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return true; }
        };

        $spec = new AndSpecification($first, $second);

        self::assertTrue($spec->isSatisfiedBy(new stdClass()));
    }

    public function test_that_is_satisfied_by_returns_false_when_first_specification_fails(): void
    {
        $first = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return false; }
        };
        $second = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return true; }
        };

        $spec = new AndSpecification($first, $second);

        self::assertFalse($spec->isSatisfiedBy(new stdClass()));
    }

    public function test_that_is_satisfied_by_returns_false_when_second_specification_fails(): void
    {
        $first = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return true; }
        };
        $second = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return false; }
        };

        $spec = new AndSpecification($first, $second);

        self::assertFalse($spec->isSatisfiedBy(new stdClass()));
    }
}
