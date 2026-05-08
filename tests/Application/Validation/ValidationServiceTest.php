<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation;

use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Common\Application\Validation\ValidationCoordinator;
use Fight\Common\Application\Validation\ValidationService;
use Fight\Common\Application\Validation\Validator;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValidationService::class)]
class ValidationServiceTest extends UnitTestCase
{
    public function test_that_constructing_without_coordinator_creates_one_internally(): void
    {
        $service = new ValidationService();

        $result = $service->validate(
            ['name' => 'Alice'],
            [['field' => 'name', 'label' => 'Name', 'rules' => 'alpha']]
        );

        self::assertInstanceOf(ApplicationData::class, $result);
        self::assertSame('Alice', $result->get('name'));
    }

    public function test_that_constructing_with_explicit_coordinator_uses_the_provided_instance(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new class implements Validator {
            public function validate(ValidationContext $context): bool
            {
                $context->addError('custom', 'From injected coordinator');

                return false;
            }
        });

        $service = new ValidationService($coordinator);

        $this->expectException(ValidationException::class);
        $service->validate([], []);
    }

    public function test_that_validate_returns_application_data_when_all_rules_pass(): void
    {
        $service = new ValidationService();

        $result = $service->validate(
            ['email' => 'user@example.com'],
            [['field' => 'email', 'label' => 'Email', 'rules' => 'email']]
        );

        self::assertInstanceOf(ApplicationData::class, $result);
        self::assertSame('user@example.com', $result->get('email'));
    }

    public function test_that_validate_returned_application_data_contains_all_input_values(): void
    {
        $service = new ValidationService();

        $result = $service->validate(
            ['name' => 'Alice', 'age' => 30],
            [['field' => 'name', 'label' => 'Name', 'rules' => 'alpha']]
        );

        self::assertSame('Alice', $result->get('name'));
        self::assertSame(30, $result->get('age'));
    }

    public function test_that_validate_throws_validation_exception_when_rules_fail(): void
    {
        $service = new ValidationService();

        $this->expectException(ValidationException::class);

        $service->validate(
            ['email' => 'not-an-email'],
            [['field' => 'email', 'label' => 'Email', 'rules' => 'email']]
        );
    }

    public function test_that_validate_throws_validation_exception_with_correct_field_errors(): void
    {
        $service = new ValidationService();

        try {
            $service->validate(
                ['email' => 'not-an-email'],
                [['field' => 'email', 'label' => 'Email', 'rules' => 'required|email']]
            );
            self::fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertArrayHasKey('email', $errors);
        }
    }

    public function test_that_add_validator_registers_a_custom_validator_implementation(): void
    {
        $service = new ValidationService();
        $service->addValidator(new class implements Validator {
            public function validate(ValidationContext $context): bool
            {
                $context->addError('custom', 'Custom validator failed');

                return false;
            }
        });

        $this->expectException(ValidationException::class);

        $service->validate(
            ['name' => 'Alice'],
            [['field' => 'name', 'label' => 'Name', 'rules' => 'alpha']]
        );
    }

    public function test_that_validate_throws_domain_exception_when_rule_entry_is_not_an_array(): void
    {
        $service = new ValidationService();

        $this->expectException(DomainException::class);

        $service->validate([], ['not-an-array']);
    }

    public function test_that_validate_throws_domain_exception_when_rule_is_missing_field_key(): void
    {
        $service = new ValidationService();

        $this->expectException(DomainException::class);

        $service->validate(
            [],
            [['label' => 'Name', 'rules' => 'required']]
        );
    }

    public function test_that_validate_throws_domain_exception_when_rule_is_missing_label_key(): void
    {
        $service = new ValidationService();

        $this->expectException(DomainException::class);

        $service->validate(
            [],
            [['field' => 'name', 'rules' => 'required']]
        );
    }

    public function test_that_validate_throws_domain_exception_when_rule_is_missing_rules_key(): void
    {
        $service = new ValidationService();

        $this->expectException(DomainException::class);

        $service->validate(
            [],
            [['field' => 'name', 'label' => 'Name']]
        );
    }

    public function test_that_validate_throws_domain_exception_when_rule_field_value_is_not_a_string(): void
    {
        $service = new ValidationService();

        $this->expectException(DomainException::class);

        $service->validate(
            ['name' => 'Alice'],
            [['field' => 123, 'label' => 'Name', 'rules' => 'alpha']]
        );
    }

    public function test_that_validate_throws_domain_exception_when_input_has_non_string_key(): void
    {
        $service = new ValidationService();

        $this->expectException(DomainException::class);

        $service->validate([0 => 'value'], []);
    }
}
