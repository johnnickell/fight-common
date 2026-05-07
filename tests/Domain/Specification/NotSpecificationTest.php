<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Specification;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Specification\NotSpecification;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NotSpecification::class)]
class NotSpecificationTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_false_when_wrapped_specification_is_satisfied(): void
    {
        $inner = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return true; }
        };

        $spec = new NotSpecification($inner);

        self::assertFalse($spec->isSatisfiedBy(new \stdClass()));
    }

    public function test_that_is_satisfied_by_returns_true_when_wrapped_specification_is_not_satisfied(): void
    {
        $inner = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return false; }
        };

        $spec = new NotSpecification($inner);

        self::assertTrue($spec->isSatisfiedBy(new \stdClass()));
    }

    public function test_that_is_satisfied_by_delegates_to_wrapped_specification_exactly_once(): void
    {
        $inner = new class extends CompositeSpecification {
            public int $callCount = 0;
            public function isSatisfiedBy(mixed $candidate): bool {
                $this->callCount++;
                return true;
            }
        };

        $spec = new NotSpecification($inner);
        $spec->isSatisfiedBy(new \stdClass());

        self::assertSame(1, $inner->callCount);
    }

    public function test_that_constructor_stores_the_wrapped_specification(): void
    {
        $inner = new class extends CompositeSpecification {
            public bool $wasCalled = false;
            public function isSatisfiedBy(mixed $candidate): bool {
                $this->wasCalled = true;
                return false;
            }
        };

        $spec = new NotSpecification($inner);
        $spec->isSatisfiedBy(new \stdClass());

        self::assertTrue($inner->wasCalled);
    }
}
