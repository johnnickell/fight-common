<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Specification;

use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\Specification\RequiredFieldSpecification;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RequiredFieldSpecification::class)]
class RequiredFieldSpecificationTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_false_when_field_is_missing(): void
    {
        $spec = new RequiredFieldSpecification('name');
        $context = new ValidationContext(new InputData([]));

        self::assertFalse($spec->isSatisfiedBy($context));
    }

    public function test_that_is_satisfied_by_returns_true_when_field_is_present_but_empty(): void
    {
        $spec = new RequiredFieldSpecification('name');
        $context = new ValidationContext(new InputData(['name' => '']));

        self::assertTrue($spec->isSatisfiedBy($context));
    }

    public function test_that_is_satisfied_by_returns_true_when_field_is_present_and_has_a_value(): void
    {
        $spec = new RequiredFieldSpecification('name');
        $context = new ValidationContext(new InputData(['name' => 'Alice']));

        self::assertTrue($spec->isSatisfiedBy($context));
    }
}
