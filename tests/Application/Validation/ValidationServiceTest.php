<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation;

use ArgumentCountError;
use Error;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Common\Application\Validation\ValidationCoordinator;
use Fight\Common\Application\Validation\ValidationService;
use Fight\Common\Application\Validation\Validator;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Throwable;

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
        } catch (ValidationException $validationException) {
            $errors = $validationException->getErrors();
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

    #[TestWith([RuntimeException::class])]
    #[TestWith([Error::class])]
    public function test_that_validator_failure_preserves_throwable_and_isolates_service_reuse(string $failureClass): void
    {
        $coordinator = new ValidationCoordinator();
        $service = new ValidationService($coordinator);
        $cause = new Error('Original cause');
        $failure = new $failureClass('Validator failed', 31, $cause);
        $throwing = $this->mock(Validator::class);
        $throwing->shouldReceive('validate')->once()->andThrow($failure);
        $skipped = $this->mock(Validator::class);
        $skipped->shouldNotReceive('validate');
        $coordinator->addRequiredValidation('missing', 'Old error');
        $service->addValidator($throwing);
        $service->addValidator($skipped);

        try {
            $service->validate([], [['field' => 'old', 'label' => 'Old', 'rules' => 'required']]);
            self::fail('Expected validator failure');
        } catch (Throwable $caught) {
            self::assertSame($failure, $caught);
            self::assertSame(31, $caught->getCode());
            self::assertSame($cause, $caught->getPrevious());
        }

        self::assertSame(['new' => 'input'], $service->validate(['new' => 'input'], [])->toArray());
        self::assertTrue($coordinator->validate(new InputData([]))->isPassed());

        $custom = $this->mock(Validator::class);
        $custom->shouldReceive('validate')->once()->andReturn(true);
        $coordinator->addValidator($custom);
        self::assertSame(
            ['email' => 'new@example.com'],
            $service->validate(
                ['email' => 'new@example.com'],
                [['field' => 'email', 'label' => 'Email', 'rules' => 'required|email']]
            )->toArray()
        );
        self::assertSame([], $service->validate([], [])->toArray());
    }

    #[DataProvider('preparationFailures')]
    public function test_that_preparation_failure_discards_queued_work(
        array $input,
        array $rules,
        string $failureClass,
        string $message
    ): void {
        $coordinator = new ValidationCoordinator();
        $service = new ValidationService($coordinator);
        $skipped = $this->mock(Validator::class);
        $skipped->shouldNotReceive('validate');
        $service->addValidator($skipped);
        $coordinator->addRequiredValidation('old', 'Old error');

        $caught = null;
        try {
            $service->validate($input, $rules);
        } catch (Throwable $failure) {
            $caught = $failure;
        }

        self::assertInstanceOf($failureClass, $caught);
        self::assertStringContainsString($message, $caught->getMessage());
        self::assertSame(['fresh' => 'value'], $service->validate(['fresh' => 'value'], [])->toArray());
        self::assertTrue($coordinator->validate(new InputData([]))->isPassed());

        // The injected coordinator must still be the service's registration boundary.
        $coordinator->addRequiredValidation('current', 'Current error');
        try {
            $service->validate([], []);
            self::fail('Expected current validation error');
        } catch (ValidationException $failure) {
            self::assertSame(['current' => ['Current error']], $failure->getErrors());
        }
        self::assertSame([], $service->validate([], [])->toArray());
    }

    public static function preparationFailures(): iterable
    {
        yield 'input keys' => [[0 => 'value'], [], DomainException::class, 'Input keys should be strings'];
        yield 'rule shape' => [[], [['field' => 'old']], DomainException::class, 'Label is required'];
        yield 'rule parsing' => [
            [],
            [['field' => 'old', 'label' => 'Old', 'rules' => 'required|unknown']],
            DomainException::class,
            'Unsupported rule name: unknown'
        ];
        yield 'partial registration' => [
            [],
            [[
                'field' => 'prepared',
                'label' => 'Prepared',
                'rules' => 'required|type',
                'errors' => ['type' => 'Invalid type']
            ]],
            ArgumentCountError::class,
            'ValidationCoordinator::addTypeValidation()'
        ];
    }

    public function test_that_partial_rule_registration_is_discarded_after_preparation_failure(): void
    {
        $service = new ValidationService();
        try {
            $service->validate([], [[
                'field' => 'prepared',
                'label' => 'Prepared',
                'rules' => 'required|type',
                'errors' => ['type' => 'Invalid type']
            ]]);
            self::fail('Expected missing type argument');
        } catch (ArgumentCountError $failure) {
            self::assertStringContainsString('ValidationCoordinator::addTypeValidation()', $failure->getMessage());
        }

        // The preceding required rule was registered before addTypeValidation rejected its arguments.
        self::assertSame([], $service->validate([], [])->toArray());
    }

    public function test_that_normal_service_results_and_errors_remain_isolated_on_reuse(): void
    {
        $service = new ValidationService();
        $rules = [['field' => 'email', 'label' => 'Email', 'rules' => 'required|email']];

        self::assertSame(
            ['email' => 'user@example.com', 'extra' => 42],
            $service->validate(['email' => 'user@example.com', 'extra' => 42], $rules)->toArray()
        );
        self::assertSame([], $service->validate([], [])->toArray());

        $coordinator = new ValidationCoordinator();
        $coordinator->addNotBlankValidation('email', 'Email cannot be blank');
        $coordinator->addEmailValidation('email', 'Email must be valid');
        $coordinator->addRequiredValidation('name', 'Name is required');
        $service = new ValidationService($coordinator);
        try {
            $service->validate(['email' => ''], []);
            self::fail('Expected accumulated validation errors');
        } catch (ValidationException $failure) {
            self::assertSame([
                'email' => ['Email cannot be blank', 'Email must be valid'],
                'name' => ['Name is required']
            ], $failure->getErrors());
        }

        self::assertSame([], $service->validate([], [])->toArray());
        try {
            $service->validate([], [['field' => 'new', 'label' => 'New', 'rules' => 'required']]);
            self::fail('Expected only the newly registered rule');
        } catch (ValidationException $failure) {
            self::assertSame(['new' => ['New is required']], $failure->getErrors());
        }
    }

    public function test_that_validate_rejects_an_unsupported_rule_name(): void
    {
        $service = new ValidationService();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unsupported rule name: unknown');

        $service->validate(
            ['name' => 'Alice'],
            [['field' => 'name', 'label' => 'Name', 'rules' => 'unknown']]
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
