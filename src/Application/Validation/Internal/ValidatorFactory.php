<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Internal;

use Fight\Common\Application\Validation\BasicValidator;
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
use Fight\Common\Application\Validation\Validator;
use Fight\Common\Domain\Specification\Specification;
use Fight\Common\Domain\Utility\Validate;
use LogicException;

/**
 * Class ValidatorFactory
 *
 * @internal Shared construction for the coordinator's existing registration paths
 */
final class ValidatorFactory
{
    /**
     * Creates a validator from a supported registration and its string arguments
     *
     * The existing convenience signatures check required arguments before construction.
     * Parsed service rules use those same signatures, not a second construction API.
     *
     * @phpstan-param array<array-key, string> $ruleArguments
     */
    public static function create(
        string $ruleType,
        string $fieldName,
        string $errorMessage,
        array $ruleArguments = []
    ): Validator {
        $specification = match ($ruleType) {
            'Required'  => new RequiredFieldSpecification($fieldName),
            'Equals'    => new EqualFieldsSpecification($fieldName, $ruleArguments[0]),
            'NotEquals' => new EqualFieldsSpecification($fieldName, $ruleArguments[0])->not(),
            'Same'      => new SameFieldsSpecification($fieldName, $ruleArguments[0]),
            'NotSame'   => new SameFieldsSpecification($fieldName, $ruleArguments[0])->not(),
            default     => new SingleFieldSpecification($fieldName, self::rule($ruleType, $ruleArguments))
        };

        return new BasicValidator($specification, $fieldName, $errorMessage);
    }

    /**
     * Creates a single-value rule with the existing argument conversions
     *
     * @phpstan-param array<array-key, string> $ruleArguments
     */
    private static function rule(string $ruleType, array $ruleArguments): Specification
    {
        return match ($ruleType) {
            'Alpha'         => new IsAlpha(),
            'AlphaDash'     => new IsAlphaDashed(),
            'AlphaNum'      => new IsAlnum(),
            'AlphaNumDash'  => new IsAlnumDashed(),
            'Blank'         => new IsBlank(),
            'Contains'      => new StringContains($ruleArguments[0]),
            'Date'          => new IsDateTime($ruleArguments[0]),
            'DateTime'      => new IsDateTime($ruleArguments[0]),
            'Digits'        => new IsDigits(),
            'Email'         => new IsEmail(),
            'Empty'         => new IsEmpty(),
            'EndsWith'      => new StringEndsWith($ruleArguments[0]),
            'ExactCount'    => new CountExact((int) $ruleArguments[0]),
            'ExactLength'   => new LengthExact((int) $ruleArguments[0]),
            'ExactNumber'   => new NumberExact(self::number($ruleArguments[0])),
            'False'         => new IsFalse(),
            'Falsy'         => new IsFalsy(),
            'InList'        => new InList($ruleArguments),
            'IpAddress'     => new IsIpAddress(),
            'IpV4Address'   => new IsIpV4Address(),
            'IpV6Address'   => new IsIpV6Address(),
            'Json'          => new IsJson(),
            'KeyIsset'      => new KeyIsset($ruleArguments[0]),
            'KeyNotEmpty'   => new KeyNotEmpty($ruleArguments[0]),
            'ListOf'        => new IsListOf($ruleArguments[0]),
            'Match'         => new IsMatch($ruleArguments[0]),
            'MaxCount'      => new CountMax((int) $ruleArguments[0]),
            'MaxLength'     => new LengthMax((int) $ruleArguments[0]),
            'MaxNumber'     => new NumberMax(self::number($ruleArguments[0])),
            'MinCount'      => new CountMin((int) $ruleArguments[0]),
            'MinLength'     => new LengthMin((int) $ruleArguments[0]),
            'MinNumber'     => new NumberMin(self::number($ruleArguments[0])),
            'NaturalNumber' => new IsNaturalNumber(),
            'NotBlank'      => new IsNotBlank(),
            'NotEmpty'      => new IsEmpty()->not(),
            'NotNull'       => new IsNull()->not(),
            'NotScalar'     => new IsScalar()->not(),
            'Null'          => new IsNull(),
            'Numeric'       => new IsNumeric(),
            'RangeCount'    => new CountRange((int) $ruleArguments[0], (int) $ruleArguments[1]),
            'RangeLength'   => new LengthRange((int) $ruleArguments[0], (int) $ruleArguments[1]),
            'RangeNumber'   => new NumberRange(self::number($ruleArguments[0]), self::number($ruleArguments[1])),
            'Scalar'        => new IsScalar(),
            'StartsWith'    => new StringStartsWith($ruleArguments[0]),
            'Time'          => new IsDateTime($ruleArguments[0]),
            'Timezone'      => new IsTimezone(),
            'True'          => new IsTrue(),
            'Truthy'        => new IsTruthy(),
            'Type'          => new IsType($ruleArguments[0]),
            'Uri'           => new IsUri(),
            'Urn'           => new IsUrn(),
            'Uuid'          => new IsUuid(),
            'WholeNumber'   => new IsWholeNumber(),
            default         => throw new LogicException(sprintf('Unsupported validation registration: %s', $ruleType))
        };
    }

    /**
     * Converts a numeric rule argument without tightening accepted string values
     */
    private static function number(string $number): int|float
    {
        return Validate::intValue($number) ? (int) $number : (float) $number;
    }
}
