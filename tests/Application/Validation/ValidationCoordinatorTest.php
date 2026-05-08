<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation;

use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Common\Application\Validation\ValidationCoordinator;
use Fight\Common\Application\Validation\Validator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValidationCoordinator::class)]
class ValidationCoordinatorTest extends UnitTestCase
{
    public function test_that_validate_returns_passed_result_when_all_validators_pass(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addEmailValidation('email', 'Invalid email');

        $result = $coordinator->validate(new InputData(['email' => 'user@example.com']));

        self::assertTrue($result->isPassed());
    }

    public function test_that_validate_passed_result_contains_application_data_with_input_values(): void
    {
        $coordinator = new ValidationCoordinator();

        $result = $coordinator->validate(new InputData(['name' => 'Alice']));

        self::assertSame('Alice', $result->getData()->get('name'));
    }

    public function test_that_validate_returns_failed_result_when_a_validator_fails(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addEmailValidation('email', 'Invalid email');

        $result = $coordinator->validate(new InputData(['email' => 'not-an-email']));

        self::assertTrue($result->isFailed());
    }

    public function test_that_validate_failed_result_contains_error_for_field_with_correct_message(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addEmailValidation('email', 'Invalid email');

        $result = $coordinator->validate(new InputData(['email' => 'not-an-email']));

        $errors = $result->getErrors();
        self::assertTrue($errors->has('email'));
        self::assertContains('Invalid email', $errors->get('email'));
    }

    public function test_that_multiple_validators_for_same_field_are_all_evaluated(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addNotBlankValidation('email', 'Email cannot be blank');
        $coordinator->addEmailValidation('email', 'Email must be valid');

        $result = $coordinator->validate(new InputData(['email' => '']));

        self::assertCount(2, $result->getErrors()->get('email'));
    }

    public function test_that_validate_accumulates_errors_for_multiple_fields(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addEmailValidation('email', 'Invalid email');
        $coordinator->addAlphaValidation('name', 'Name must be alpha');

        $result = $coordinator->validate(new InputData([
            'email' => 'not-an-email',
            'name'  => '123',
        ]));

        $errors = $result->getErrors();
        self::assertTrue($errors->has('email'));
        self::assertTrue($errors->has('name'));
    }

    public function test_that_validators_are_reset_after_validate_so_coordinator_can_be_reused(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addEmailValidation('email', 'Invalid email');

        $coordinator->validate(new InputData(['email' => 'not-an-email']));

        $result = $coordinator->validate(new InputData(['email' => 'not-an-email']));

        self::assertTrue($result->isPassed());
    }

    public function test_that_add_validator_registers_a_custom_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new class implements Validator {
            public function validate(ValidationContext $context): bool
            {
                $context->addError('field', 'Custom error');

                return false;
            }
        });

        $result = $coordinator->validate(new InputData([]));

        self::assertTrue($result->isFailed());
        self::assertContains('Custom error', $result->getErrors()->get('field'));
    }

    public function test_that_add_alpha_validation_adds_alpha_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addAlphaValidation('name', 'Must be alpha');

        $result = $coordinator->validate(new InputData(['name' => '123']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be alpha', $result->getErrors()->get('name'));
    }

    public function test_that_add_alpha_dash_validation_adds_alpha_dash_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addAlphaDashValidation('slug', 'Must be alpha dash');

        $result = $coordinator->validate(new InputData(['slug' => '!@#']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be alpha dash', $result->getErrors()->get('slug'));
    }

    public function test_that_add_alpha_num_validation_adds_alpha_num_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addAlphaNumValidation('code', 'Must be alphanumeric');

        $result = $coordinator->validate(new InputData(['code' => '!@#']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be alphanumeric', $result->getErrors()->get('code'));
    }

    public function test_that_add_alpha_num_dash_validation_adds_alpha_num_dash_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addAlphaNumDashValidation('code', 'Must be alphanumeric dash');

        $result = $coordinator->validate(new InputData(['code' => '!@#']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be alphanumeric dash', $result->getErrors()->get('code'));
    }

    public function test_that_add_blank_validation_adds_blank_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addBlankValidation('field', 'Must be blank');

        $result = $coordinator->validate(new InputData(['field' => 'not blank']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be blank', $result->getErrors()->get('field'));
    }

    public function test_that_add_contains_validation_adds_contains_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addContainsValidation('text', 'Must contain hello', 'hello');

        $result = $coordinator->validate(new InputData(['text' => 'world']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must contain hello', $result->getErrors()->get('text'));
    }

    public function test_that_add_date_validation_adds_date_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addDateValidation('date', 'Must be a valid date', 'Y-m-d');

        $result = $coordinator->validate(new InputData(['date' => 'not-a-date']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a valid date', $result->getErrors()->get('date'));
    }

    public function test_that_add_date_time_validation_adds_date_time_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addDateTimeValidation('dt', 'Must be a valid datetime', 'Y-m-d H:i:s');

        $result = $coordinator->validate(new InputData(['dt' => 'not-a-datetime']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a valid datetime', $result->getErrors()->get('dt'));
    }

    public function test_that_add_digits_validation_adds_digits_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addDigitsValidation('code', 'Must be digits');

        $result = $coordinator->validate(new InputData(['code' => 'abc']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be digits', $result->getErrors()->get('code'));
    }

    public function test_that_add_email_validation_adds_email_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addEmailValidation('email', 'Must be a valid email');

        $result = $coordinator->validate(new InputData(['email' => 'not-an-email']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a valid email', $result->getErrors()->get('email'));
    }

    public function test_that_add_empty_validation_adds_empty_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addEmptyValidation('field', 'Must be empty');

        $result = $coordinator->validate(new InputData(['field' => 'not empty']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be empty', $result->getErrors()->get('field'));
    }

    public function test_that_add_ends_with_validation_adds_ends_with_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addEndsWithValidation('text', 'Must end with world', 'world');

        $result = $coordinator->validate(new InputData(['text' => 'hello']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must end with world', $result->getErrors()->get('text'));
    }

    public function test_that_add_equals_validation_adds_equal_fields_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addEqualsValidation('a', 'Fields must be equal', 'b');

        $result = $coordinator->validate(new InputData(['a' => 'Alice', 'b' => 'Bob']));

        self::assertTrue($result->isFailed());
        self::assertContains('Fields must be equal', $result->getErrors()->get('a'));
    }

    public function test_that_add_exact_count_validation_adds_exact_count_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addExactCountValidation('items', 'Must have 3 items', '3');

        $result = $coordinator->validate(new InputData(['items' => ['a', 'b']]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must have 3 items', $result->getErrors()->get('items'));
    }

    public function test_that_add_exact_length_validation_adds_exact_length_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addExactLengthValidation('name', 'Must be 5 chars', '5');

        $result = $coordinator->validate(new InputData(['name' => 'abc']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be 5 chars', $result->getErrors()->get('name'));
    }

    public function test_that_add_exact_number_validation_with_integer_value_adds_integer_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addExactNumberValidation('num', 'Must be 10', '10');

        $result = $coordinator->validate(new InputData(['num' => 5]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be 10', $result->getErrors()->get('num'));
    }

    public function test_that_add_exact_number_validation_with_float_value_adds_float_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addExactNumberValidation('num', 'Must be 1.5', '1.5');

        $result = $coordinator->validate(new InputData(['num' => 2.5]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be 1.5', $result->getErrors()->get('num'));
    }

    public function test_that_add_false_validation_adds_false_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addFalseValidation('flag', 'Must be false');

        $result = $coordinator->validate(new InputData(['flag' => true]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be false', $result->getErrors()->get('flag'));
    }

    public function test_that_add_falsy_validation_adds_falsy_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addFalsyValidation('flag', 'Must be falsy');

        $result = $coordinator->validate(new InputData(['flag' => 'truthy']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be falsy', $result->getErrors()->get('flag'));
    }

    public function test_that_add_in_list_validation_adds_in_list_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addInListValidation('type', 'Must be in list', 'a', 'b', 'c');

        $result = $coordinator->validate(new InputData(['type' => 'd']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be in list', $result->getErrors()->get('type'));
    }

    public function test_that_add_ip_address_validation_adds_ip_address_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addIpAddressValidation('ip', 'Must be an IP address');

        $result = $coordinator->validate(new InputData(['ip' => 'not-an-ip']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be an IP address', $result->getErrors()->get('ip'));
    }

    public function test_that_add_ip_v4_address_validation_adds_ip_v4_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addIpV4AddressValidation('ip', 'Must be an IPv4 address');

        $result = $coordinator->validate(new InputData(['ip' => 'not-an-ip']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be an IPv4 address', $result->getErrors()->get('ip'));
    }

    public function test_that_add_ip_v6_address_validation_adds_ip_v6_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addIpV6AddressValidation('ip', 'Must be an IPv6 address');

        $result = $coordinator->validate(new InputData(['ip' => 'not-an-ipv6']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be an IPv6 address', $result->getErrors()->get('ip'));
    }

    public function test_that_add_json_validation_adds_json_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addJsonValidation('data', 'Must be valid JSON');

        $result = $coordinator->validate(new InputData(['data' => '{invalid']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be valid JSON', $result->getErrors()->get('data'));
    }

    public function test_that_add_key_isset_validation_adds_key_isset_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addKeyIssetValidation('data', 'Key must be set', 'key');

        $result = $coordinator->validate(new InputData(['data' => ['other' => 'value']]));

        self::assertTrue($result->isFailed());
        self::assertContains('Key must be set', $result->getErrors()->get('data'));
    }

    public function test_that_add_key_not_empty_validation_adds_key_not_empty_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addKeyNotEmptyValidation('data', 'Key must not be empty', 'key');

        $result = $coordinator->validate(new InputData(['data' => ['key' => '']]));

        self::assertTrue($result->isFailed());
        self::assertContains('Key must not be empty', $result->getErrors()->get('data'));
    }

    public function test_that_add_list_of_validation_adds_list_of_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addListOfValidation('items', 'Must be list of strings', 'string');

        $result = $coordinator->validate(new InputData(['items' => [1, 2, 3]]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be list of strings', $result->getErrors()->get('items'));
    }

    public function test_that_add_match_validation_adds_match_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addMatchValidation('slug', 'Must match pattern', '/^\d+$/');

        $result = $coordinator->validate(new InputData(['slug' => 'abc']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must match pattern', $result->getErrors()->get('slug'));
    }

    public function test_that_add_max_count_validation_adds_max_count_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addMaxCountValidation('items', 'Too many items', '3');

        $result = $coordinator->validate(new InputData(['items' => ['a', 'b', 'c', 'd']]));

        self::assertTrue($result->isFailed());
        self::assertContains('Too many items', $result->getErrors()->get('items'));
    }

    public function test_that_add_max_length_validation_adds_max_length_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addMaxLengthValidation('name', 'Too long', '5');

        $result = $coordinator->validate(new InputData(['name' => 'toolongstring']));

        self::assertTrue($result->isFailed());
        self::assertContains('Too long', $result->getErrors()->get('name'));
    }

    public function test_that_add_max_number_validation_with_integer_value_adds_integer_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addMaxNumberValidation('num', 'Too large', '10');

        $result = $coordinator->validate(new InputData(['num' => 15]));

        self::assertTrue($result->isFailed());
        self::assertContains('Too large', $result->getErrors()->get('num'));
    }

    public function test_that_add_max_number_validation_with_float_value_adds_float_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addMaxNumberValidation('num', 'Too large', '1.5');

        $result = $coordinator->validate(new InputData(['num' => 2.5]));

        self::assertTrue($result->isFailed());
        self::assertContains('Too large', $result->getErrors()->get('num'));
    }

    public function test_that_add_min_count_validation_adds_min_count_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addMinCountValidation('items', 'Too few items', '3');

        $result = $coordinator->validate(new InputData(['items' => ['a', 'b']]));

        self::assertTrue($result->isFailed());
        self::assertContains('Too few items', $result->getErrors()->get('items'));
    }

    public function test_that_add_min_length_validation_adds_min_length_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addMinLengthValidation('name', 'Too short', '5');

        $result = $coordinator->validate(new InputData(['name' => 'abc']));

        self::assertTrue($result->isFailed());
        self::assertContains('Too short', $result->getErrors()->get('name'));
    }

    public function test_that_add_min_number_validation_with_integer_value_adds_integer_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addMinNumberValidation('num', 'Too small', '5');

        $result = $coordinator->validate(new InputData(['num' => 3]));

        self::assertTrue($result->isFailed());
        self::assertContains('Too small', $result->getErrors()->get('num'));
    }

    public function test_that_add_min_number_validation_with_float_value_adds_float_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addMinNumberValidation('num', 'Too small', '2.5');

        $result = $coordinator->validate(new InputData(['num' => 1.5]));

        self::assertTrue($result->isFailed());
        self::assertContains('Too small', $result->getErrors()->get('num'));
    }

    public function test_that_add_natural_number_validation_adds_natural_number_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addNaturalNumberValidation('num', 'Must be a natural number');

        $result = $coordinator->validate(new InputData(['num' => 0]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a natural number', $result->getErrors()->get('num'));
    }

    public function test_that_add_not_blank_validation_adds_not_blank_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addNotBlankValidation('field', 'Must not be blank');

        $result = $coordinator->validate(new InputData(['field' => '']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must not be blank', $result->getErrors()->get('field'));
    }

    public function test_that_add_not_empty_validation_adds_not_empty_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addNotEmptyValidation('field', 'Must not be empty');

        $result = $coordinator->validate(new InputData(['field' => '']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must not be empty', $result->getErrors()->get('field'));
    }

    public function test_that_add_not_equals_validation_adds_not_equal_fields_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addNotEqualsValidation('a', 'Fields must not be equal', 'b');

        $result = $coordinator->validate(new InputData(['a' => 'Alice', 'b' => 'Alice']));

        self::assertTrue($result->isFailed());
        self::assertContains('Fields must not be equal', $result->getErrors()->get('a'));
    }

    public function test_that_add_not_null_validation_adds_not_null_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addNotNullValidation('field', 'Must not be null');

        $result = $coordinator->validate(new InputData(['field' => null]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must not be null', $result->getErrors()->get('field'));
    }

    public function test_that_add_not_same_validation_adds_not_same_fields_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addNotSameValidation('a', 'Fields must not be the same', 'b');

        $result = $coordinator->validate(new InputData(['a' => 'Alice', 'b' => 'Alice']));

        self::assertTrue($result->isFailed());
        self::assertContains('Fields must not be the same', $result->getErrors()->get('a'));
    }

    public function test_that_add_not_scalar_validation_adds_not_scalar_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addNotScalarValidation('field', 'Must not be scalar');

        $result = $coordinator->validate(new InputData(['field' => 'scalar value']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must not be scalar', $result->getErrors()->get('field'));
    }

    public function test_that_add_null_validation_adds_null_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addNullValidation('field', 'Must be null');

        $result = $coordinator->validate(new InputData(['field' => 'not null']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be null', $result->getErrors()->get('field'));
    }

    public function test_that_add_numeric_validation_adds_numeric_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addNumericValidation('field', 'Must be numeric');

        $result = $coordinator->validate(new InputData(['field' => 'abc']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be numeric', $result->getErrors()->get('field'));
    }

    public function test_that_add_range_count_validation_adds_range_count_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addRangeCountValidation('items', 'Count out of range', '2', '4');

        $result = $coordinator->validate(new InputData(['items' => ['a']]));

        self::assertTrue($result->isFailed());
        self::assertContains('Count out of range', $result->getErrors()->get('items'));
    }

    public function test_that_add_range_length_validation_adds_range_length_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addRangeLengthValidation('name', 'Length out of range', '3', '6');

        $result = $coordinator->validate(new InputData(['name' => 'ab']));

        self::assertTrue($result->isFailed());
        self::assertContains('Length out of range', $result->getErrors()->get('name'));
    }

    public function test_that_add_range_number_validation_with_integer_values_adds_integer_range_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addRangeNumberValidation('num', 'Out of range', '5', '10');

        $result = $coordinator->validate(new InputData(['num' => 3]));

        self::assertTrue($result->isFailed());
        self::assertContains('Out of range', $result->getErrors()->get('num'));
    }

    public function test_that_add_range_number_validation_with_float_values_adds_float_range_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addRangeNumberValidation('num', 'Out of range', '1.5', '3.5');

        $result = $coordinator->validate(new InputData(['num' => 0.5]));

        self::assertTrue($result->isFailed());
        self::assertContains('Out of range', $result->getErrors()->get('num'));
    }

    public function test_that_add_required_validation_adds_required_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addRequiredValidation('name', 'Name is required');

        $result = $coordinator->validate(new InputData([]));

        self::assertTrue($result->isFailed());
        self::assertContains('Name is required', $result->getErrors()->get('name'));
    }

    public function test_that_add_same_validation_adds_same_fields_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addSameValidation('a', 'Fields must be the same', 'b');

        $result = $coordinator->validate(new InputData(['a' => 'Alice', 'b' => 'Bob']));

        self::assertTrue($result->isFailed());
        self::assertContains('Fields must be the same', $result->getErrors()->get('a'));
    }

    public function test_that_add_scalar_validation_adds_scalar_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addScalarValidation('field', 'Must be scalar');

        $result = $coordinator->validate(new InputData(['field' => ['not', 'scalar']]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be scalar', $result->getErrors()->get('field'));
    }

    public function test_that_add_starts_with_validation_adds_starts_with_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addStartsWithValidation('text', 'Must start with hello', 'hello');

        $result = $coordinator->validate(new InputData(['text' => 'world hello']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must start with hello', $result->getErrors()->get('text'));
    }

    public function test_that_add_time_validation_adds_time_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addTimeValidation('time', 'Must be a valid time', 'H:i:s');

        $result = $coordinator->validate(new InputData(['time' => 'not-a-time']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a valid time', $result->getErrors()->get('time'));
    }

    public function test_that_add_timezone_validation_adds_timezone_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addTimezoneValidation('tz', 'Must be a valid timezone');

        $result = $coordinator->validate(new InputData(['tz' => 'Not/Valid/Timezone']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a valid timezone', $result->getErrors()->get('tz'));
    }

    public function test_that_add_true_validation_adds_true_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addTrueValidation('flag', 'Must be true');

        $result = $coordinator->validate(new InputData(['flag' => false]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be true', $result->getErrors()->get('flag'));
    }

    public function test_that_add_truthy_validation_adds_truthy_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addTruthyValidation('flag', 'Must be truthy');

        $result = $coordinator->validate(new InputData(['flag' => '']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be truthy', $result->getErrors()->get('flag'));
    }

    public function test_that_add_type_validation_adds_type_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addTypeValidation('field', 'Must be a string', 'string');

        $result = $coordinator->validate(new InputData(['field' => 123]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a string', $result->getErrors()->get('field'));
    }

    public function test_that_add_uri_validation_adds_uri_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addUriValidation('uri', 'Must be a valid URI');

        $result = $coordinator->validate(new InputData(['uri' => 'not a uri']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a valid URI', $result->getErrors()->get('uri'));
    }

    public function test_that_add_urn_validation_adds_urn_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addUrnValidation('urn', 'Must be a valid URN');

        $result = $coordinator->validate(new InputData(['urn' => 'not-a-urn']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a valid URN', $result->getErrors()->get('urn'));
    }

    public function test_that_add_uuid_validation_adds_uuid_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addUuidValidation('id', 'Must be a valid UUID');

        $result = $coordinator->validate(new InputData(['id' => 'not-a-uuid']));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a valid UUID', $result->getErrors()->get('id'));
    }

    public function test_that_add_whole_number_validation_adds_whole_number_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addWholeNumberValidation('num', 'Must be a whole number');

        $result = $coordinator->validate(new InputData(['num' => -1]));

        self::assertTrue($result->isFailed());
        self::assertContains('Must be a whole number', $result->getErrors()->get('num'));
    }
}
