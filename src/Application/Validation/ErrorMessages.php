<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation;

/**
 * Class ErrorMessages
 */
final class ErrorMessages
{
    public const string ALPHA = '%s may only contain alphabetic characters';
    public const string ALPHA_DASH = '%s may only contain alphabetic characters, hyphens, or underscores';
    public const string ALPHA_NUM = '%s may only contain alphanumeric characters';
    public const string ALPHA_NUM_DASH = '%s may only contain alphanumeric characters, hyphens, or underscores';
    public const string BLANK = '%s must be blank';
    public const string CONTAINS = '%s must contain "%s"';
    public const string DATE = '%s must be a valid date in format "%s"';
    public const string DATE_TIME = '%s must be a valid date/time in format "%s"';
    public const string DIGITS = '%s may only contain digits';
    public const string EMAIL = '%s must be a valid email address';
    public const string EMPTY = '%s must be empty';
    public const string ENDS_WITH = '%s must end with "%s"';
    public const string EQUALS = '%s must equal the value in the %s field';
    public const string EXACT_COUNT = '%s must contain exactly %d items';
    public const string EXACT_LENGTH = '%s must contain exactly %d characters';
    public const string EXACT_NUMBER = '%s must be equal to %s';
    public const string FALSE = '%s must be false';
    public const string FALSY = '%s must evaluate to false';
    public const string IN_LIST = '%s must be one of {{list}}';
    public const string IP_ADDRESS = '%s must be a valid IP address';
    public const string IP_V4_ADDRESS = '%s must be a valid IP V4 address';
    public const string IP_V6_ADDRESS = '%s must be a valid IP V6 address';
    public const string JSON = '%s must be a valid JSON-formatted string';
    public const string KEY_ISSET = '%s must have the %s key set';
    public const string KEY_NOT_EMPTY = '%s must have the %s key set to a non-empty value';
    public const string LIST_OF = '%s must be a list of type: %s';
    public const string MATCH = '%s must match the regular expression "%s"';
    public const string MAX_COUNT = '%s must contain no more than %d items';
    public const string MAX_LENGTH = '%s must contain no more than %d characters';
    public const string MAX_NUMBER = '%s must be less than or equal to %s';
    public const string MIN_COUNT = '%s must contain no less than %d items';
    public const string MIN_LENGTH = '%s must contain no less than %d characters';
    public const string MIN_NUMBER = '%s must be greater than or equal to %s';
    public const string NATURAL_NUMBER = '%s must be a whole number greater than zero';
    public const string NOT_BLANK = '%s cannot be blank';
    public const string NOT_EMPTY = '%s cannot be empty';
    public const string NOT_EQUALS = '%s must not equal the value in the %s field';
    public const string NOT_NULL = '%s cannot be null';
    public const string NOT_SAME = '%s must not be the same as the value in the %s field';
    public const string NOT_SCALAR = '%s cannot be scalar';
    public const string NULL = '%s must be null';
    public const string NUMERIC = '%s must be numeric';
    public const string RANGE_COUNT = '%s must contain between %d and %d items';
    public const string RANGE_LENGTH = '%s must contain between %d and %d characters';
    public const string RANGE_NUMBER = '%s must be between %s and %s';
    public const string REQUIRED = '%s is required';
    public const string SAME = '%s must be the same as the value in the %s field';
    public const string SCALAR = '%s must be scalar';
    public const string STARTS_WITH = '%s must start with "%s"';
    public const string TIME = '%s must be a valid time in format "%s"';
    public const string TIMEZONE = '%s must be a valid timezone';
    public const string TRUE = '%s must be true';
    public const string TRUTHY = '%s must evaluate to true';
    public const string TYPE = '%s must be a value of type: %s';
    public const string URI = '%s must be a valid URI';
    public const string URN = '%s must be a valid URN';
    public const string UUID = '%s must be a valid UUID';
    public const string WHOLE_NUMBER = '%s must be a whole number';
}
