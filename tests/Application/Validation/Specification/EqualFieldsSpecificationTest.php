<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Specification;

use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\Specification\EqualFieldsSpecification;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EqualFieldsSpecification::class)]
class EqualFieldsSpecificationTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_both_fields_have_the_same_value(): void
    {
        $spec = new EqualFieldsSpecification('password', 'confirm');
        $context = new ValidationContext(new InputData(['password' => 'secret', 'confirm' => 'secret']));

        self::assertTrue($spec->isSatisfiedBy($context));
    }

    public function test_that_is_satisfied_by_returns_false_when_fields_have_different_values(): void
    {
        $spec = new EqualFieldsSpecification('password', 'confirm');
        $context = new ValidationContext(new InputData(['password' => 'secret', 'confirm' => 'wrong']));

        self::assertFalse($spec->isSatisfiedBy($context));
    }

    public function test_that_is_satisfied_by_returns_true_when_either_field_is_missing(): void
    {
        $spec = new EqualFieldsSpecification('password', 'confirm');
        $context = new ValidationContext(new InputData(['password' => 'secret']));

        self::assertTrue($spec->isSatisfiedBy($context));
    }
}
