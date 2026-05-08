<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation;

use Fight\Common\Application\Validation\BasicValidator;
use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BasicValidator::class)]
class BasicValidatorTest extends UnitTestCase
{
    public function test_that_validate_returns_true_when_specification_is_satisfied(): void
    {
        $spec = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool
            {
                return true;
            }
        };
        $validator = new BasicValidator($spec, 'field', 'Error message');
        $context = new ValidationContext(new InputData(['field' => 'value']));

        self::assertTrue($validator->validate($context));
    }

    public function test_that_validate_returns_false_when_specification_is_not_satisfied(): void
    {
        $spec = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool
            {
                return false;
            }
        };
        $validator = new BasicValidator($spec, 'field', 'Error message');
        $context = new ValidationContext(new InputData(['field' => 'value']));

        self::assertFalse($validator->validate($context));
    }

    public function test_that_validate_does_not_add_error_to_context_when_specification_passes(): void
    {
        $spec = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool
            {
                return true;
            }
        };
        $validator = new BasicValidator($spec, 'field', 'Error message');
        $context = new ValidationContext(new InputData(['field' => 'value']));

        $validator->validate($context);

        self::assertFalse($context->hasErrors());
    }

    public function test_that_validate_adds_error_to_context_when_specification_fails(): void
    {
        $spec = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool
            {
                return false;
            }
        };
        $validator = new BasicValidator($spec, 'field', 'Error message');
        $context = new ValidationContext(new InputData(['field' => 'value']));

        $validator->validate($context);

        self::assertTrue($context->hasErrors());
    }

    public function test_that_validate_adds_error_under_the_correct_field_name(): void
    {
        $spec = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool
            {
                return false;
            }
        };
        $validator = new BasicValidator($spec, 'email', 'Invalid email');
        $context = new ValidationContext(new InputData(['email' => 'value']));

        $validator->validate($context);

        self::assertArrayHasKey('email', $context->getErrors());
    }

    public function test_that_validate_adds_the_correct_error_message_to_the_context(): void
    {
        $spec = new class extends CompositeSpecification {
            public function isSatisfiedBy(mixed $candidate): bool
            {
                return false;
            }
        };
        $validator = new BasicValidator($spec, 'field', 'Must be valid');
        $context = new ValidationContext(new InputData(['field' => 'value']));

        $validator->validate($context);

        self::assertContains('Must be valid', $context->getErrors()['field']);
    }
}
