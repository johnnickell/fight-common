<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation;

use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValidationContext::class)]
class ValidationContextTest extends UnitTestCase
{
    public function test_that_get_returns_value_for_an_existing_field(): void
    {
        $context = new ValidationContext(new InputData(['name' => 'Alice']));

        self::assertSame('Alice', $context->get('name'));
    }

    public function test_that_get_throws_key_exception_when_field_is_not_in_input(): void
    {
        $context = new ValidationContext(new InputData([]));

        $this->expectException(KeyException::class);
        $context->get('missing');
    }

    public function test_that_has_errors_returns_false_when_no_errors_have_been_added(): void
    {
        $context = new ValidationContext(new InputData([]));

        self::assertFalse($context->hasErrors());
    }

    public function test_that_has_errors_returns_true_after_an_error_is_added(): void
    {
        $context = new ValidationContext(new InputData([]));
        $context->addError('field', 'Error message');

        self::assertTrue($context->hasErrors());
    }

    public function test_that_get_errors_returns_empty_array_when_no_errors_have_been_added(): void
    {
        $context = new ValidationContext(new InputData([]));

        self::assertSame([], $context->getErrors());
    }

    public function test_that_get_errors_returns_error_message_nested_under_field_name(): void
    {
        $context = new ValidationContext(new InputData([]));
        $context->addError('email', 'Email is invalid');

        $errors = $context->getErrors();

        self::assertArrayHasKey('email', $errors);
        self::assertContains('Email is invalid', $errors['email']);
    }

    public function test_that_get_errors_accumulates_distinct_messages_for_the_same_field(): void
    {
        $context = new ValidationContext(new InputData([]));
        $context->addError('email', 'Error one');
        $context->addError('email', 'Error two');

        $errors = $context->getErrors();

        self::assertCount(2, $errors['email']);
        self::assertContains('Error one', $errors['email']);
        self::assertContains('Error two', $errors['email']);
    }

    public function test_that_add_error_deduplicates_identical_messages_for_the_same_field(): void
    {
        $context = new ValidationContext(new InputData([]));
        $context->addError('email', 'Duplicate error');
        $context->addError('email', 'Duplicate error');

        self::assertCount(1, $context->getErrors()['email']);
    }

    public function test_that_get_errors_includes_errors_for_multiple_fields(): void
    {
        $context = new ValidationContext(new InputData([]));
        $context->addError('email', 'Email error');
        $context->addError('name', 'Name error');

        $errors = $context->getErrors();

        self::assertArrayHasKey('email', $errors);
        self::assertArrayHasKey('name', $errors);
    }
}
