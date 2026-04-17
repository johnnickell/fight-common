<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation;

use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Application\Validation\Data\ErrorData;
use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\Rule\CountExact;
use Fight\Common\Application\Validation\Rule\CountMax;
use Fight\Common\Application\Validation\Rule\CountMin;
use Fight\Common\Application\Validation\Rule\CountRange;
use Fight\Common\Application\Validation\Rule\InList;
use Fight\Common\Application\Validation\Rule\IsAlnum;
use Fight\Common\Application\Validation\Rule\IsAlnumDashed;
use Fight\Common\Application\Validation\Rule\IsAlpha;
use Fight\Common\Application\Validation\Rule\IsAlphaDashed;
use Fight\Common\Application\Validation\Rule\IsBlank;
use Fight\Common\Application\Validation\Rule\IsDateTime;
use Fight\Common\Application\Validation\Rule\IsDigits;
use Fight\Common\Application\Validation\Rule\IsEmail;
use Fight\Common\Application\Validation\Rule\IsEmpty;
use Fight\Common\Application\Validation\Rule\IsFalse;
use Fight\Common\Application\Validation\Rule\IsFalsy;
use Fight\Common\Application\Validation\Rule\IsIpAddress;
use Fight\Common\Application\Validation\Rule\IsIpV4Address;
use Fight\Common\Application\Validation\Rule\IsIpV6Address;
use Fight\Common\Application\Validation\Rule\IsJson;
use Fight\Common\Application\Validation\Rule\IsListOf;
use Fight\Common\Application\Validation\Rule\IsMatch;
use Fight\Common\Application\Validation\Rule\IsNaturalNumber;
use Fight\Common\Application\Validation\Rule\IsNotBlank;
use Fight\Common\Application\Validation\Rule\IsNull;
use Fight\Common\Application\Validation\Rule\IsNumeric;
use Fight\Common\Application\Validation\Rule\IsScalar;
use Fight\Common\Application\Validation\Rule\IsTimezone;
use Fight\Common\Application\Validation\Rule\IsTrue;
use Fight\Common\Application\Validation\Rule\IsTruthy;
use Fight\Common\Application\Validation\Rule\IsType;
use Fight\Common\Application\Validation\Rule\IsUri;
use Fight\Common\Application\Validation\Rule\IsUrn;
use Fight\Common\Application\Validation\Rule\IsUuid;
use Fight\Common\Application\Validation\Rule\IsWholeNumber;
use Fight\Common\Application\Validation\Rule\KeyIsset;
use Fight\Common\Application\Validation\Rule\KeyNotEmpty;
use Fight\Common\Application\Validation\Rule\LengthExact;
use Fight\Common\Application\Validation\Rule\LengthMax;
use Fight\Common\Application\Validation\Rule\LengthMin;
use Fight\Common\Application\Validation\Rule\LengthRange;
use Fight\Common\Application\Validation\Rule\NumberExact;
use Fight\Common\Application\Validation\Rule\NumberMax;
use Fight\Common\Application\Validation\Rule\NumberMin;
use Fight\Common\Application\Validation\Rule\NumberRange;
use Fight\Common\Application\Validation\Rule\StringContains;
use Fight\Common\Application\Validation\Rule\StringEndsWith;
use Fight\Common\Application\Validation\Rule\StringStartsWith;
use Fight\Common\Application\Validation\Specification\EqualFieldsSpecification;
use Fight\Common\Application\Validation\Specification\RequiredFieldSpecification;
use Fight\Common\Application\Validation\Specification\SameFieldsSpecification;
use Fight\Common\Application\Validation\Specification\SingleFieldSpecification;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class ValidationCoordinator
 */
final class ValidationCoordinator
{
    protected ArrayList $validators;

    /**
     * Constructs ValidationCoordinator
     */
    public function __construct()
    {
        $this->resetValidators();
    }

    /**
     * Validates input data
     */
    public function validate(InputData $input): ValidationResult
    {
        $context = $this->createContext($input);

        $valid = $this->validators->reduce(
            function (bool $valid, Validator $validator) use ($context) {
                if (!$validator->validate($context)) {
                    $valid = false;
                }

                return $valid;
            },
            $valid = true
        );

        if ($context->hasErrors() || !$valid) {
            $result = ValidationResult::failed(
                new ErrorData($context->getErrors())
            );
        } else {
            $result = ValidationResult::passed(
                new ApplicationData($input->toArray())
            );
        }

        $this->resetValidators();

        return $result;
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
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsAlpha()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is alphabetic dashed
     */
    public function addAlphaDashValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsAlphaDashed()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is alphanumeric
     */
    public function addAlphaNumValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsAlnum()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is alphanumeric dashed
     */
    public function addAlphaNumDashValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsAlnumDashed()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is blank
     */
    public function addBlankValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsBlank()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string contains a search string
     */
    public function addContainsValidation(string $fieldName, string $errorMessage, string $search): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new StringContains($search)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is a valid date
     */
    public function addDateValidation(string $fieldName, string $errorMessage, string $format): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsDateTime($format)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is a valid date/time
     */
    public function addDateTimeValidation(string $fieldName, string $errorMessage, string $format): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsDateTime($format)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field only contains digits
     */
    public function addDigitsValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsDigits()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is an email address
     */
    public function addEmailValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsEmail()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is empty
     */
    public function addEmptyValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsEmpty()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string ends with a search string
     */
    public function addEndsWithValidation(string $fieldName, string $errorMessage, string $search): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new StringEndsWith($search)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts two fields are equal
     */
    public function addEqualsValidation(string $fieldName, string $errorMessage, string $comparisonField): void
    {
        $this->addValidator(
            new BasicValidator(
                new EqualFieldsSpecification(
                    $fieldName,
                    $comparisonField
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field has an exact count
     */
    public function addExactCountValidation(string $fieldName, string $errorMessage, string $count): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new CountExact((int) $count)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is an exact length
     */
    public function addExactLengthValidation(string $fieldName, string $errorMessage, string $length): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new LengthExact((int) $length)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a number matches another number
     */
    public function addExactNumberValidation(string $fieldName, string $errorMessage, string $number): void
    {
        $intVal = Validate::intValue($number);

        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new NumberExact($intVal ? (int) $number : (float) $number)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is false
     */
    public function addFalseValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsFalse()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is falsy
     */
    public function addFalsyValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsFalsy()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field matches one of a list of values
     */
    public function addInListValidation(string $fieldName, string $errorMessage, string ...$list): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new InList($list)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is an IP address
     */
    public function addIpAddressValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsIpAddress()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is an IP V4 address
     */
    public function addIpV4AddressValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsIpV4Address()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is an IP V6 address
     */
    public function addIpV6AddressValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsIpV6Address()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is JSON formatted
     */
    public function addJsonValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsJson()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a key is set
     */
    public function addKeyIssetValidation(string $fieldName, string $errorMessage, string $key): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new KeyIsset($key)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a key is not empty
     */
    public function addKeyNotEmptyValidation(string $fieldName, string $errorMessage, string $key): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new KeyNotEmpty($key)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is list of type
     */
    public function addListOfValidation(string $fieldName, string $errorMessage, string $type): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsListOf($type)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field matches a regular expression
     */
    public function addMatchValidation(string $fieldName, string $errorMessage, string $pattern): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsMatch($pattern)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field has a count equal to or less than
     * a given count maximum
     */
    public function addMaxCountValidation(string $fieldName, string $errorMessage, string $maxCount): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new CountMax((int) $maxCount)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is less than or equal to a
     * maximum length
     */
    public function addMaxLengthValidation(string $fieldName, string $errorMessage, string $maxLength): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new LengthMax((int) $maxLength)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a number is less than or equal to a
     * maximum number
     */
    public function addMaxNumberValidation(string $fieldName, string $errorMessage, string $maxNumber): void
    {
        $intVal = Validate::intValue($maxNumber);

        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new NumberMax(
                        $intVal ? (int) $maxNumber : (float) $maxNumber
                    )
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field has a count equal to or greater
     * than a given count minimum
     */
    public function addMinCountValidation(string $fieldName, string $errorMessage, string $minCount): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new CountMin((int) $minCount)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string is greater than or equal to a
     * minimum length
     */
    public function addMinLengthValidation(string $fieldName, string $errorMessage, string $minLength): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new LengthMin((int) $minLength)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a number is greater than or equal to a
     * minimum number
     */
    public function addMinNumberValidation(string $fieldName, string $errorMessage, string $minNumber): void
    {
        $intVal = Validate::intValue($minNumber);

        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new NumberMin(
                        $intVal ? (int) $minNumber : (float) $minNumber
                    )
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is a natural number
     */
    public function addNaturalNumberValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsNaturalNumber()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is not blank
     */
    public function addNotBlankValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsNotBlank()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is not empty
     */
    public function addNotEmptyValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsEmpty()->not()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts two fields are not equal
     */
    public function addNotEqualsValidation(string $fieldName, string $errorMessage, string $comparisonField): void
    {
        $this->addValidator(
            new BasicValidator(
                new EqualFieldsSpecification(
                    $fieldName,
                    $comparisonField
                )->not(),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is not null
     */
    public function addNotNullValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsNull()->not()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts two fields are not the same
     */
    public function addNotSameValidation(string $fieldName, string $errorMessage, string $comparisonField): void
    {
        $this->addValidator(
            new BasicValidator(
                new SameFieldsSpecification(
                    $fieldName,
                    $comparisonField
                )->not(),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is not scalar
     */
    public function addNotScalarValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsScalar()->not()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is null
     */
    public function addNullValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsNull()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is numeric
     */
    public function addNumericValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsNumeric()
                ),
                $fieldName,
                $errorMessage
            )
        );
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
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new CountRange((int) $minCount, (int) $maxCount)
                ),
                $fieldName,
                $errorMessage
            )
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
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new LengthRange((int) $minLength, (int) $maxLength)
                ),
                $fieldName,
                $errorMessage
            )
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
        $minIntVal = Validate::intValue($minNumber);
        $maxIntVal = Validate::intValue($maxNumber);

        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new NumberRange(
                        $minIntVal ? (int) $minNumber : (float) $minNumber,
                        $maxIntVal ? (int) $maxNumber : (float) $maxNumber
                    )
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is required
     */
    public function addRequiredValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new RequiredFieldSpecification($fieldName),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts two fields are the same
     */
    public function addSameValidation(string $fieldName, string $errorMessage, string $comparisonField): void
    {
        $this->addValidator(
            new BasicValidator(
                new SameFieldsSpecification(
                    $fieldName,
                    $comparisonField
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is scalar
     */
    public function addScalarValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsScalar()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a string starts with a search string
     */
    public function addStartsWithValidation(string $fieldName, string $errorMessage, string $search): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new StringStartsWith($search)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is a valid time
     */
    public function addTimeValidation(string $fieldName, string $errorMessage, string $format): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsDateTime($format)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is a timezone
     */
    public function addTimezoneValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsTimezone()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is true
     */
    public function addTrueValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsTrue()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is truthy
     */
    public function addTruthyValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsTruthy()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is of a type
     */
    public function addTypeValidation(string $fieldName, string $errorMessage, string $type): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsType($type)
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is a URI
     */
    public function addUriValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsUri()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is a URN
     */
    public function addUrnValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsUrn()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is a UUID
     */
    public function addUuidValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsUuid()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Adds a validation that asserts a field is a whole number
     */
    public function addWholeNumberValidation(string $fieldName, string $errorMessage): void
    {
        $this->addValidator(
            new BasicValidator(
                new SingleFieldSpecification(
                    $fieldName,
                    new IsWholeNumber()
                ),
                $fieldName,
                $errorMessage
            )
        );
    }

    /**
     * Resets the list of validators
     */
    private function resetValidators(): void
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
