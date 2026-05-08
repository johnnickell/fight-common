<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Specification;

use stdClass;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Specification\OrSpecification;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OrSpecification::class)]
class OrSpecificationTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_both_specifications_are_satisfied(): void
    {
        $first = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return true; }
        };
        $second = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return true; }
        };

        $spec = new OrSpecification($first, $second);

        self::assertTrue($spec->isSatisfiedBy(new stdClass()));
    }

    public function test_that_is_satisfied_by_returns_true_when_only_first_specification_is_satisfied(): void
    {
        $first = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return true; }
        };
        $second = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return false; }
        };

        $spec = new OrSpecification($first, $second);

        self::assertTrue($spec->isSatisfiedBy(new stdClass()));
    }

    public function test_that_is_satisfied_by_returns_true_when_only_second_specification_is_satisfied(): void
    {
        $first = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return false; }
        };
        $second = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return true; }
        };

        $spec = new OrSpecification($first, $second);

        self::assertTrue($spec->isSatisfiedBy(new stdClass()));
    }

    public function test_that_is_satisfied_by_returns_false_when_both_specifications_fail(): void
    {
        $first = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return false; }
        };
        $second = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return false; }
        };

        $spec = new OrSpecification($first, $second);

        self::assertFalse($spec->isSatisfiedBy(new stdClass()));
    }
}
