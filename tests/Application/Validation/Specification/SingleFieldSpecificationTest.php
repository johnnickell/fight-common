<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Specification;

use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\Specification\SingleFieldSpecification;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SingleFieldSpecification::class)]
class SingleFieldSpecificationTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_field_is_missing(): void
    {
        $rule = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return false; }
        };
        $spec = new SingleFieldSpecification('email', $rule);
        $context = new ValidationContext(new InputData([]));

        self::assertTrue($spec->isSatisfiedBy($context));
    }

    public function test_that_is_satisfied_by_delegates_to_inner_rule_when_field_is_present(): void
    {
        $rule = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool { return false; }
        };
        $spec = new SingleFieldSpecification('name', $rule);
        $context = new ValidationContext(new InputData(['name' => 'Alice']));

        self::assertFalse($spec->isSatisfiedBy($context));
    }
}
