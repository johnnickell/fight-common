<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Specification;

use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\Specification\SameFieldsSpecification;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SameFieldsSpecification::class)]
class SameFieldsSpecificationTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_both_fields_are_strictly_identical(): void
    {
        $spec = new SameFieldsSpecification('password', 'confirm');
        $context = new ValidationContext(new InputData(['password' => 'secret', 'confirm' => 'secret']));

        self::assertTrue($spec->isSatisfiedBy($context));
    }

    public function test_that_is_satisfied_by_returns_false_when_fields_have_same_value_but_different_types(): void
    {
        $spec = new SameFieldsSpecification('a', 'b');
        $context = new ValidationContext(new InputData(['a' => 1, 'b' => '1']));

        self::assertFalse($spec->isSatisfiedBy($context));
    }

    public function test_that_is_satisfied_by_returns_true_when_either_field_is_missing(): void
    {
        $spec = new SameFieldsSpecification('password', 'confirm');
        $context = new ValidationContext(new InputData(['password' => 'secret']));

        self::assertTrue($spec->isSatisfiedBy($context));
    }
}
