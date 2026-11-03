<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation;

use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Application\Validation\Data\ErrorData;
use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\Internal\ValidatorFactory;
use Fight\Common\Domain\Collection\ArrayList;

/**
 * Class ValidationCoordinator
 */
final class ValidationCoordinator
{
    /**
     * @var ArrayList<Validator>
     */
    private ArrayList $validators;

    /**
     * Constructs ValidationCoordinator
     */
    public function __construct()
    {
        $this->resetValidators();
    }

    /**
     * Validates input data and clears queued validators on every exit
     */
    public function validate(InputData $input): ValidationResult
    {
        try {
            $context = $this->createContext($input);

            $valid = $this->validators->reduce(
                function (bool $valid, Validator $validator) use ($context) {
                    if (!$validator->validate($context)) {
                        return false;
                    }

                    return $valid;
                },
                true
            );

            if ($context->hasErrors() || !$valid) {
                return ValidationResult::failed(
                    new ErrorData($context->getErrors())
                );
            }

            return ValidationResult::passed(
                new ApplicationData($input->toArray())
            );
        } finally {
            $this->resetValidators();
        }
    }

    /**
     * Adds a validator
     */
    public function addValidator(Validator $validator): void
    {
        $this->validators->add($validator);
    }

    /**
     * Adds a validation that asserts a string is alphabetic
     */
    public function addAlphaValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Alpha', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a string is alphabetic dashed
     */
    public function addAlphaDashValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('AlphaDash', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a string is alphanumeric
     */
    public function addAlphaNumValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('AlphaNum', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a string is alphanumeric dashed
     */
    public function addAlphaNumDashValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('AlphaNumDash', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is blank
     */
    public function addBlankValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Blank', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a string contains a search string
     */
    public function addContainsValidation(string $fieldName, string $errorMessage, string $search): void
    {
        $this->addValidator(ValidatorFactory::create('Contains', $fieldName, $errorMessage, [$search]));
    }

    /**
     * Adds a validation that asserts a field is a valid date
     */
    public function addDateValidation(string $fieldName, string $errorMessage, string $format): void
    {
        $this->addValidator(ValidatorFactory::create('Date', $fieldName, $errorMessage, [$format]));
    }

    /**
     * Adds a validation that asserts a field is a valid date/time
     */
    public function addDateTimeValidation(string $fieldName, string $errorMessage, string $format): void
    {
        $this->addValidator(ValidatorFactory::create('DateTime', $fieldName, $errorMessage, [$format]));
    }

    /**
     * Adds a validation that asserts a field only contains digits
     */
    public function addDigitsValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Digits', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a string is an email address
     */
    public function addEmailValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Email', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is empty
     */
    public function addEmptyValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Empty', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a string ends with a search string
     */
    public function addEndsWithValidation(string $fieldName, string $errorMessage, string $search): void
    {
        $this->addValidator(ValidatorFactory::create('EndsWith', $fieldName, $errorMessage, [$search]));
    }

    /**
     * Adds a validation that asserts two fields are equal
     */
    public function addEqualsValidation(string $fieldName, string $errorMessage, string $comparisonField): void
    {
        $this->addValidator(ValidatorFactory::create('Equals', $fieldName, $errorMessage, [$comparisonField]));
    }

    /**
     * Adds a validation that asserts a field has an exact count
     */
    public function addExactCountValidation(string $fieldName, string $errorMessage, string $count): void
    {
        $this->addValidator(ValidatorFactory::create('ExactCount', $fieldName, $errorMessage, [$count]));
    }

    /**
     * Adds a validation that asserts a string is an exact length
     */
    public function addExactLengthValidation(string $fieldName, string $errorMessage, string $length): void
    {
        $this->addValidator(ValidatorFactory::create('ExactLength', $fieldName, $errorMessage, [$length]));
    }

    /**
     * Adds a validation that asserts a number matches another number
     */
    public function addExactNumberValidation(string $fieldName, string $errorMessage, string $number): void
    {
        $this->addValidator(ValidatorFactory::create('ExactNumber', $fieldName, $errorMessage, [$number]));
    }

    /**
     * Adds a validation that asserts a field is false
     */
    public function addFalseValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('False', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is falsy
     */
    public function addFalsyValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Falsy', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field matches one of a list of values
     */
    public function addInListValidation(string $fieldName, string $errorMessage, string ...$list): void
    {
        $this->addValidator(ValidatorFactory::create('InList', $fieldName, $errorMessage, $list));
    }

    /**
     * Adds a validation that asserts a string is an IP address
     */
    public function addIpAddressValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('IpAddress', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a string is an IP V4 address
     */
    public function addIpV4AddressValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('IpV4Address', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a string is an IP V6 address
     */
    public function addIpV6AddressValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('IpV6Address', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a string is JSON formatted
     */
    public function addJsonValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Json', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a key is set
     */
    public function addKeyIssetValidation(string $fieldName, string $errorMessage, string $key): void
    {
        $this->addValidator(ValidatorFactory::create('KeyIsset', $fieldName, $errorMessage, [$key]));
    }

    /**
     * Adds a validation that asserts a key is not empty
     */
    public function addKeyNotEmptyValidation(string $fieldName, string $errorMessage, string $key): void
    {
        $this->addValidator(ValidatorFactory::create('KeyNotEmpty', $fieldName, $errorMessage, [$key]));
    }

    /**
     * Adds a validation that asserts a field is list of type
     */
    public function addListOfValidation(string $fieldName, string $errorMessage, string $type): void
    {
        $this->addValidator(ValidatorFactory::create('ListOf', $fieldName, $errorMessage, [$type]));
    }

    /**
     * Adds a validation that asserts a field matches a regular expression
     */
    public function addMatchValidation(string $fieldName, string $errorMessage, string $pattern): void
    {
        $this->addValidator(ValidatorFactory::create('Match', $fieldName, $errorMessage, [$pattern]));
    }

    /**
     * Adds a validation for a maximum field count
     */
    public function addMaxCountValidation(string $fieldName, string $errorMessage, string $maxCount): void
    {
        $this->addValidator(ValidatorFactory::create('MaxCount', $fieldName, $errorMessage, [$maxCount]));
    }

    /**
     * Adds a validation for a maximum string length
     */
    public function addMaxLengthValidation(string $fieldName, string $errorMessage, string $maxLength): void
    {
        $this->addValidator(ValidatorFactory::create('MaxLength', $fieldName, $errorMessage, [$maxLength]));
    }

    /**
     * Adds a validation for a maximum numeric value
     */
    public function addMaxNumberValidation(string $fieldName, string $errorMessage, string $maxNumber): void
    {
        $this->addValidator(ValidatorFactory::create('MaxNumber', $fieldName, $errorMessage, [$maxNumber]));
    }

    /**
     * Adds a validation for a minimum field count
     */
    public function addMinCountValidation(string $fieldName, string $errorMessage, string $minCount): void
    {
        $this->addValidator(ValidatorFactory::create('MinCount', $fieldName, $errorMessage, [$minCount]));
    }

    /**
     * Adds a validation for a minimum string length
     */
    public function addMinLengthValidation(string $fieldName, string $errorMessage, string $minLength): void
    {
        $this->addValidator(ValidatorFactory::create('MinLength', $fieldName, $errorMessage, [$minLength]));
    }

    /**
     * Adds a validation for a minimum numeric value
     */
    public function addMinNumberValidation(string $fieldName, string $errorMessage, string $minNumber): void
    {
        $this->addValidator(ValidatorFactory::create('MinNumber', $fieldName, $errorMessage, [$minNumber]));
    }

    /**
     * Adds a validation that asserts a field is a natural number
     */
    public function addNaturalNumberValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('NaturalNumber', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is not blank
     */
    public function addNotBlankValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('NotBlank', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is not empty
     */
    public function addNotEmptyValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('NotEmpty', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts two fields are not equal
     */
    public function addNotEqualsValidation(string $fieldName, string $errorMessage, string $comparisonField): void
    {
        $this->addValidator(ValidatorFactory::create('NotEquals', $fieldName, $errorMessage, [$comparisonField]));
    }

    /**
     * Adds a validation that asserts a field is not null
     */
    public function addNotNullValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('NotNull', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts two fields are not the same
     */
    public function addNotSameValidation(string $fieldName, string $errorMessage, string $comparisonField): void
    {
        $this->addValidator(ValidatorFactory::create('NotSame', $fieldName, $errorMessage, [$comparisonField]));
    }

    /**
     * Adds a validation that asserts a field is not scalar
     */
    public function addNotScalarValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('NotScalar', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is null
     */
    public function addNullValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Null', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is numeric
     */
    public function addNumericValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Numeric', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field has a count within a defined range
     */
    public function addRangeCountValidation(
        string $fieldName,
        string $errorMessage,
        string $minCount,
        string $maxCount
    ): void {
        $this->addValidator(
            ValidatorFactory::create('RangeCount', $fieldName, $errorMessage, [$minCount, $maxCount])
        );
    }

    /**
     * Adds a validation that asserts a string length is within a range
     */
    public function addRangeLengthValidation(
        string $fieldName,
        string $errorMessage,
        string $minLength,
        string $maxLength
    ): void {
        $this->addValidator(
            ValidatorFactory::create('RangeLength', $fieldName, $errorMessage, [$minLength, $maxLength])
        );
    }

    /**
     * Adds a validation that asserts a number is within a range
     */
    public function addRangeNumberValidation(
        string $fieldName,
        string $errorMessage,
        string $minNumber,
        string $maxNumber
    ): void {
        $this->addValidator(
            ValidatorFactory::create('RangeNumber', $fieldName, $errorMessage, [$minNumber, $maxNumber])
        );
    }

    /**
     * Adds a validation that asserts a field is required
     */
    public function addRequiredValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Required', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts two fields are the same
     */
    public function addSameValidation(string $fieldName, string $errorMessage, string $comparisonField): void
    {
        $this->addValidator(ValidatorFactory::create('Same', $fieldName, $errorMessage, [$comparisonField]));
    }

    /**
     * Adds a validation that asserts a field is scalar
     */
    public function addScalarValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Scalar', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a string starts with a search string
     */
    public function addStartsWithValidation(string $fieldName, string $errorMessage, string $search): void
    {
        $this->addValidator(ValidatorFactory::create('StartsWith', $fieldName, $errorMessage, [$search]));
    }

    /**
     * Adds a validation that asserts a field is a valid time
     */
    public function addTimeValidation(string $fieldName, string $errorMessage, string $format): void
    {
        $this->addValidator(ValidatorFactory::create('Time', $fieldName, $errorMessage, [$format]));
    }

    /**
     * Adds a validation that asserts a field is a timezone
     */
    public function addTimezoneValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Timezone', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is true
     */
    public function addTrueValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('True', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is truthy
     */
    public function addTruthyValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Truthy', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is of a type
     */
    public function addTypeValidation(string $fieldName, string $errorMessage, string $type): void
    {
        $this->addValidator(ValidatorFactory::create('Type', $fieldName, $errorMessage, [$type]));
    }

    /**
     * Adds a validation that asserts a field is a URI
     */
    public function addUriValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Uri', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is a URN
     */
    public function addUrnValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Urn', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is a UUID
     */
    public function addUuidValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('Uuid', $fieldName, $errorMessage));
    }

    /**
     * Adds a validation that asserts a field is a whole number
     */
    public function addWholeNumberValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(ValidatorFactory::create('WholeNumber', $fieldName, $errorMessage));
    }

    /**
     * Clears queued validators without executing them
     *
     * Preparation failures can discard pending work on the same coordinator.
     * This does not reset consumer-owned validator state or undo side effects.
     */
    public function resetValidators(): void
    {
        $this->validators = ArrayList::of(Validator::class);
    }

    /**
     * Creates validation context
     */
    private function createContext(InputData $input): ValidationContext
    {
        return new ValidationContext($input);
    }
}
