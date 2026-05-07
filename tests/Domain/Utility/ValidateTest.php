<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Utility;

use ArrayObject;
use Countable;
use Fight\Common\Domain\Exception\RuntimeException;
use Fight\Common\Domain\Type\Equatable;
use Fight\Common\Domain\Type\Type;
use Fight\Common\Domain\Utility\Validate;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Stringable;

#[CoversClass(Validate::class)]
class ValidateTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Type checks
    // -------------------------------------------------------------------------

    public function test_that_is_scalar_returns_true_for_scalar_values(): void
    {
        self::assertTrue(Validate::isScalar('string'));
        self::assertTrue(Validate::isScalar(42));
        self::assertTrue(Validate::isScalar(3.14));
        self::assertTrue(Validate::isScalar(true));
    }

    public function test_that_is_scalar_returns_false_for_non_scalar_values(): void
    {
        self::assertFalse(Validate::isScalar(null));
        self::assertFalse(Validate::isScalar([]));
        self::assertFalse(Validate::isScalar(new stdClass()));
    }

    public function test_that_is_bool_returns_true_for_booleans(): void
    {
        self::assertTrue(Validate::isBool(true));
        self::assertTrue(Validate::isBool(false));
    }

    public function test_that_is_bool_returns_false_for_non_booleans(): void
    {
        self::assertFalse(Validate::isBool(0));
        self::assertFalse(Validate::isBool('true'));
        self::assertFalse(Validate::isBool(null));
    }

    public function test_that_is_float_returns_true_for_floats(): void
    {
        self::assertTrue(Validate::isFloat(3.14));
        self::assertTrue(Validate::isFloat(0.0));
    }

    public function test_that_is_float_returns_false_for_non_floats(): void
    {
        self::assertFalse(Validate::isFloat(42));
        self::assertFalse(Validate::isFloat('3.14'));
    }

    public function test_that_is_int_returns_true_for_integers(): void
    {
        self::assertTrue(Validate::isInt(0));
        self::assertTrue(Validate::isInt(-5));
        self::assertTrue(Validate::isInt(PHP_INT_MAX));
    }

    public function test_that_is_int_returns_false_for_non_integers(): void
    {
        self::assertFalse(Validate::isInt(3.14));
        self::assertFalse(Validate::isInt('42'));
        self::assertFalse(Validate::isInt(true));
    }

    public function test_that_is_string_returns_true_for_strings(): void
    {
        self::assertTrue(Validate::isString(''));
        self::assertTrue(Validate::isString('hello'));
    }

    public function test_that_is_string_returns_false_for_non_strings(): void
    {
        self::assertFalse(Validate::isString(42));
        self::assertFalse(Validate::isString(null));
        self::assertFalse(Validate::isString([]));
    }

    public function test_that_is_array_returns_true_for_arrays(): void
    {
        self::assertTrue(Validate::isArray([]));
        self::assertTrue(Validate::isArray([1, 2, 3]));
    }

    public function test_that_is_array_returns_false_for_non_arrays(): void
    {
        self::assertFalse(Validate::isArray('array'));
        self::assertFalse(Validate::isArray(new ArrayObject()));
    }

    public function test_that_is_object_returns_true_for_objects(): void
    {
        self::assertTrue(Validate::isObject(new stdClass()));
        self::assertTrue(Validate::isObject(new RuntimeException('test')));
    }

    public function test_that_is_object_returns_false_for_non_objects(): void
    {
        self::assertFalse(Validate::isObject([]));
        self::assertFalse(Validate::isObject('object'));
    }

    public function test_that_is_callable_returns_true_for_callables(): void
    {
        self::assertTrue(Validate::isCallable(function () {}));
        self::assertTrue(Validate::isCallable('strlen'));
        self::assertTrue(Validate::isCallable([Validate::class, 'isString']));
    }

    public function test_that_is_callable_returns_false_for_non_callables(): void
    {
        self::assertFalse(Validate::isCallable('not_a_function'));
        self::assertFalse(Validate::isCallable(42));
    }

    public function test_that_is_null_returns_true_for_null(): void
    {
        self::assertTrue(Validate::isNull(null));
    }

    public function test_that_is_null_returns_false_for_non_null(): void
    {
        self::assertFalse(Validate::isNull(0));
        self::assertFalse(Validate::isNull(''));
        self::assertFalse(Validate::isNull(false));
    }

    public function test_that_is_not_null_returns_true_for_non_null(): void
    {
        self::assertTrue(Validate::isNotNull(0));
        self::assertTrue(Validate::isNotNull(''));
        self::assertTrue(Validate::isNotNull(false));
    }

    public function test_that_is_not_null_returns_false_for_null(): void
    {
        self::assertFalse(Validate::isNotNull(null));
    }

    public function test_that_is_true_returns_true_only_for_boolean_true(): void
    {
        self::assertTrue(Validate::isTrue(true));
    }

    public function test_that_is_true_returns_false_for_truthy_values(): void
    {
        self::assertFalse(Validate::isTrue(1));
        self::assertFalse(Validate::isTrue('true'));
        self::assertFalse(Validate::isTrue(false));
    }

    public function test_that_is_false_returns_true_only_for_boolean_false(): void
    {
        self::assertTrue(Validate::isFalse(false));
    }

    public function test_that_is_false_returns_false_for_falsy_values(): void
    {
        self::assertFalse(Validate::isFalse(0));
        self::assertFalse(Validate::isFalse(''));
        self::assertFalse(Validate::isFalse(true));
    }

    public function test_that_is_empty_returns_true_for_empty_values(): void
    {
        self::assertTrue(Validate::isEmpty(''));
        self::assertTrue(Validate::isEmpty([]));
        self::assertTrue(Validate::isEmpty(0));
        self::assertTrue(Validate::isEmpty(null));
    }

    public function test_that_is_not_empty_returns_true_for_non_empty_values(): void
    {
        self::assertTrue(Validate::isNotEmpty('hello'));
        self::assertTrue(Validate::isNotEmpty([1]));
        self::assertTrue(Validate::isNotEmpty(1));
    }

    public function test_that_is_blank_returns_true_for_whitespace_strings(): void
    {
        self::assertTrue(Validate::isBlank(''));
        self::assertTrue(Validate::isBlank('   '));
        self::assertTrue(Validate::isBlank("\t\n"));
    }

    public function test_that_is_blank_returns_false_for_non_blank_strings(): void
    {
        self::assertFalse(Validate::isBlank('hello'));
        self::assertFalse(Validate::isBlank('  a  '));
    }

    public function test_that_is_blank_returns_false_for_non_string_castable(): void
    {
        self::assertFalse(Validate::isBlank([]));
        self::assertFalse(Validate::isBlank(new stdClass()));
    }

    public function test_that_is_not_blank_returns_true_for_non_blank_strings(): void
    {
        self::assertTrue(Validate::isNotBlank('hello'));
        self::assertTrue(Validate::isNotBlank('  a  '));
    }

    public function test_that_is_not_blank_returns_false_for_blank_strings(): void
    {
        self::assertFalse(Validate::isNotBlank(''));
        self::assertFalse(Validate::isNotBlank('   '));
    }

    public function test_that_is_not_blank_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isNotBlank([]));
        self::assertFalse(Validate::isNotBlank(new stdClass()));
    }

    // -------------------------------------------------------------------------
    // Format checks
    // -------------------------------------------------------------------------

    public function test_that_is_alpha_returns_true_for_alphabetic_strings(): void
    {
        self::assertTrue(Validate::isAlpha('hello'));
        self::assertTrue(Validate::isAlpha('HELLO'));
        self::assertTrue(Validate::isAlpha(''));
    }

    public function test_that_is_alpha_returns_false_for_non_alphabetic_strings(): void
    {
        self::assertFalse(Validate::isAlpha('hello123'));
        self::assertFalse(Validate::isAlpha('hello-world'));
    }

    public function test_that_is_alpha_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isAlpha([]));
    }

    public function test_that_is_alnum_returns_true_for_alphanumeric_strings(): void
    {
        self::assertTrue(Validate::isAlnum('hello123'));
        self::assertTrue(Validate::isAlnum('ABC'));
        self::assertTrue(Validate::isAlnum('123'));
    }

    public function test_that_is_alnum_returns_false_for_non_alphanumeric_strings(): void
    {
        self::assertFalse(Validate::isAlnum('hello-world'));
        self::assertFalse(Validate::isAlnum('hello world'));
    }

    public function test_that_is_alnum_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isAlnum([]));
    }

    public function test_that_is_alpha_dash_returns_true_for_alphabetic_dash_strings(): void
    {
        self::assertTrue(Validate::isAlphaDash('hello'));
        self::assertTrue(Validate::isAlphaDash('hello-world'));
        self::assertTrue(Validate::isAlphaDash('hello_world'));
    }

    public function test_that_is_alpha_dash_returns_false_for_non_alphabetic_dash_strings(): void
    {
        self::assertFalse(Validate::isAlphaDash('hello123'));
        self::assertFalse(Validate::isAlphaDash('hello world'));
    }

    public function test_that_is_alpha_dash_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isAlphaDash([]));
    }

    public function test_that_is_alnum_dash_returns_true_for_alphanumeric_dash_strings(): void
    {
        self::assertTrue(Validate::isAlnumDash('hello-world-123'));
        self::assertTrue(Validate::isAlnumDash('hello_world'));
        self::assertTrue(Validate::isAlnumDash('abc123'));
    }

    public function test_that_is_alnum_dash_returns_false_for_strings_with_spaces(): void
    {
        self::assertFalse(Validate::isAlnumDash('hello world'));
        self::assertFalse(Validate::isAlnumDash('hello.world'));
    }

    public function test_that_is_alnum_dash_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isAlnumDash([]));
    }

    public function test_that_is_digits_returns_true_for_digit_only_strings(): void
    {
        self::assertTrue(Validate::isDigits('123'));
        self::assertTrue(Validate::isDigits('0'));
    }

    public function test_that_is_digits_returns_false_for_non_digit_strings(): void
    {
        self::assertFalse(Validate::isDigits('12.3'));
        self::assertFalse(Validate::isDigits('-5'));
        self::assertFalse(Validate::isDigits('12a'));
    }

    public function test_that_is_digits_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isDigits([]));
    }

    public function test_that_is_numeric_returns_true_for_numeric_values(): void
    {
        self::assertTrue(Validate::isNumeric('123'));
        self::assertTrue(Validate::isNumeric('3.14'));
        self::assertTrue(Validate::isNumeric('-5'));
        self::assertTrue(Validate::isNumeric('1e5'));
    }

    public function test_that_is_numeric_returns_false_for_non_numeric_strings(): void
    {
        self::assertFalse(Validate::isNumeric('abc'));
        self::assertFalse(Validate::isNumeric('12abc'));
    }

    public function test_that_is_numeric_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isNumeric([]));
    }

    // -------------------------------------------------------------------------
    // Internet format checks
    // -------------------------------------------------------------------------

    public function test_that_is_email_returns_true_for_valid_email(): void
    {
        self::assertTrue(Validate::isEmail('user@example.com'));
        self::assertTrue(Validate::isEmail('user+tag@example.org'));
    }

    public function test_that_is_email_returns_false_for_invalid_email(): void
    {
        self::assertFalse(Validate::isEmail('not-an-email'));
        self::assertFalse(Validate::isEmail('@example.com'));
        self::assertFalse(Validate::isEmail('user@'));
        self::assertFalse(Validate::isEmail([]));
    }

    public function test_that_is_ip_address_returns_true_for_valid_ip(): void
    {
        self::assertTrue(Validate::isIpAddress('192.168.1.1'));
        self::assertTrue(Validate::isIpAddress('::1'));
    }

    public function test_that_is_ip_address_returns_false_for_invalid_ip(): void
    {
        self::assertFalse(Validate::isIpAddress('999.999.999.999'));
        self::assertFalse(Validate::isIpAddress('not-an-ip'));
    }

    public function test_that_is_ip_address_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isIpAddress([]));
    }

    public function test_that_is_ip_v4_address_returns_true_for_valid_ipv4(): void
    {
        self::assertTrue(Validate::isIpV4Address('192.168.1.1'));
        self::assertTrue(Validate::isIpV4Address('0.0.0.0'));
        self::assertTrue(Validate::isIpV4Address('255.255.255.255'));
    }

    public function test_that_is_ip_v4_address_returns_false_for_ipv6(): void
    {
        self::assertFalse(Validate::isIpV4Address('::1'));
        self::assertFalse(Validate::isIpV4Address('2001:db8::1'));
    }

    public function test_that_is_ip_v4_address_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isIpV4Address([]));
    }

    public function test_that_is_ip_v6_address_returns_true_for_valid_ipv6(): void
    {
        self::assertTrue(Validate::isIpV6Address('::1'));
        self::assertTrue(Validate::isIpV6Address('2001:db8::1'));
    }

    public function test_that_is_ip_v6_address_returns_false_for_ipv4(): void
    {
        self::assertFalse(Validate::isIpV6Address('192.168.1.1'));
    }

    public function test_that_is_ip_v6_address_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isIpV6Address([]));
    }

    public function test_that_is_uri_returns_true_for_valid_uri(): void
    {
        self::assertTrue(Validate::isUri('https://example.com/path?query=value#fragment'));
        self::assertTrue(Validate::isUri('http://user:pass@host:8080/path'));
        self::assertTrue(Validate::isUri('urn:isbn:0451450523'));
    }

    public function test_that_is_uri_returns_false_for_invalid_uri(): void
    {
        self::assertFalse(Validate::isUri('not a uri'));
        self::assertFalse(Validate::isUri('//no-scheme'));
        self::assertFalse(Validate::isUri([]));
    }

    public function test_that_is_uri_returns_true_for_uri_with_empty_path(): void
    {
        // authority present, path is empty — exercises the isValidUriPath empty-path early return
        self::assertTrue(Validate::isUri('http://example.com'));
    }

    public function test_that_is_uri_returns_true_for_uri_with_ipv4_host(): void
    {
        // exercises the IPv4 preg_match branch in isValidAuthHost
        self::assertTrue(Validate::isUri('http://192.168.1.1/path'));
    }

    public function test_that_is_uri_returns_true_for_uri_with_empty_userinfo_host(): void
    {
        // authority "user@" produces an empty host — exercises the empty-host early return
        self::assertTrue(Validate::isUri('http://user@/path'));
    }

    public function test_that_is_uri_returns_true_for_uri_with_ipv6_literal_host(): void
    {
        // exercises isValidIpLiteral with a valid IPv6 address
        self::assertTrue(Validate::isUri('http://[::1]/path'));
    }

    public function test_that_is_uri_returns_true_for_uri_with_ipvfuture_literal_host(): void
    {
        // exercises the IPvFuture branch inside isValidIpLiteral
        self::assertTrue(Validate::isUri('http://[vF.1:test]/path'));
    }

    public function test_that_is_uri_returns_false_for_uri_with_invalid_path_characters(): void
    {
        // { and } are not allowed in a URI path — exercises the isValidUriPath false return
        self::assertFalse(Validate::isUri('http://example.com/{bad}'));
    }

    public function test_that_is_uri_returns_false_for_uri_with_invalid_query_characters(): void
    {
        // < is not an allowed query character — exercises the isValidUriQuery false return
        self::assertFalse(Validate::isUri('http://example.com/path?key=<bad>'));
    }

    public function test_that_is_uri_returns_false_for_uri_with_invalid_fragment_characters(): void
    {
        // < is not an allowed fragment character — exercises the isValidUriFragment false return
        self::assertFalse(Validate::isUri('http://example.com/path#<bad>'));
    }

    public function test_that_is_uri_returns_false_for_uri_with_invalid_userinfo(): void
    {
        // < in userinfo is not allowed — exercises the isValidAuthUser false return
        self::assertFalse(Validate::isUri('http://us<er@example.com/path'));
    }

    public function test_that_is_uri_returns_false_for_uri_with_invalid_host_characters(): void
    {
        // { in a reg-name host is not allowed — exercises the isValidAuthHost reg-name false return
        self::assertFalse(Validate::isUri('http://exam{ple.com/path'));
    }

    public function test_that_is_uri_returns_false_for_uri_with_malformed_ip_literal(): void
    {
        // host "a[b" contains [ but is not a valid IP literal bracket pair
        self::assertFalse(Validate::isUri('http://a[b/path'));
    }

    public function test_that_is_uri_returns_false_for_uri_with_invalid_ip_literal_content(): void
    {
        // [invalid] has correct brackets but is neither IPvFuture nor valid IPv6
        self::assertFalse(Validate::isUri('http://[invalid]/path'));
    }

    public function test_that_is_urn_returns_true_for_valid_urn(): void
    {
        self::assertTrue(Validate::isUrn('urn:isbn:0451450523'));
        self::assertTrue(Validate::isUrn('urn:example:a123,z456'));
    }

    public function test_that_is_urn_returns_false_for_invalid_urn(): void
    {
        self::assertFalse(Validate::isUrn('urn:urn:foo'));
        self::assertFalse(Validate::isUrn('http://example.com'));
        self::assertFalse(Validate::isUrn([]));
    }

    public function test_that_is_uuid_returns_true_for_valid_uuid(): void
    {
        self::assertTrue(Validate::isUuid('550e8400-e29b-41d4-a716-446655440000'));
        self::assertTrue(Validate::isUuid('urn:uuid:550e8400-e29b-41d4-a716-446655440000'));
        self::assertTrue(Validate::isUuid('{550e8400-e29b-41d4-a716-446655440000}'));
    }

    public function test_that_is_uuid_returns_false_for_invalid_uuid(): void
    {
        self::assertFalse(Validate::isUuid('not-a-uuid'));
        self::assertFalse(Validate::isUuid('550e8400-e29b-41d4-a716'));
        self::assertFalse(Validate::isUuid([]));
    }

    public function test_that_is_timezone_returns_true_for_valid_timezone(): void
    {
        self::assertTrue(Validate::isTimezone('UTC'));
        self::assertTrue(Validate::isTimezone('America/New_York'));
        self::assertTrue(Validate::isTimezone('Europe/London'));
    }

    public function test_that_is_timezone_returns_false_for_invalid_timezone(): void
    {
        self::assertFalse(Validate::isTimezone('Not/ATimezone'));
        self::assertFalse(Validate::isTimezone(''));
        self::assertFalse(Validate::isTimezone([]));
    }

    public function test_that_is_json_returns_true_for_valid_json(): void
    {
        self::assertTrue(Validate::isJson('{"key":"value"}'));
        self::assertTrue(Validate::isJson('[1,2,3]'));
        self::assertTrue(Validate::isJson('"string"'));
        self::assertTrue(Validate::isJson('null'));
    }

    public function test_that_is_json_returns_false_for_invalid_json(): void
    {
        self::assertFalse(Validate::isJson('{invalid}'));
        self::assertFalse(Validate::isJson(''));
        self::assertFalse(Validate::isJson([]));
    }

    // -------------------------------------------------------------------------
    // Pattern/String checks
    // -------------------------------------------------------------------------

    public function test_that_is_match_returns_true_for_matching_pattern(): void
    {
        self::assertTrue(Validate::isMatch('hello123', '/\A[a-z0-9]+\z/i'));
        self::assertTrue(Validate::isMatch('2024-01-01', '/\A\d{4}-\d{2}-\d{2}\z/'));
    }

    public function test_that_is_match_returns_false_for_non_matching_pattern(): void
    {
        self::assertFalse(Validate::isMatch('hello world', '/\A[a-z0-9]+\z/'));
        self::assertFalse(Validate::isMatch([], '/pattern/'));
    }

    public function test_that_contains_returns_true_when_value_contains_search(): void
    {
        self::assertTrue(Validate::contains('hello world', 'world'));
        self::assertTrue(Validate::contains('hello', ''));
    }

    public function test_that_contains_returns_false_when_value_does_not_contain_search(): void
    {
        self::assertFalse(Validate::contains('hello', 'xyz'));
        self::assertFalse(Validate::contains([], 'foo'));
    }

    public function test_that_starts_with_returns_true_for_matching_prefix(): void
    {
        self::assertTrue(Validate::startsWith('hello world', 'hello'));
    }

    public function test_that_starts_with_returns_false_for_non_matching_prefix(): void
    {
        self::assertFalse(Validate::startsWith('hello world', 'world'));
        self::assertFalse(Validate::startsWith([], 'foo'));
    }

    public function test_that_ends_with_returns_true_for_matching_suffix(): void
    {
        self::assertTrue(Validate::endsWith('hello world', 'world'));
    }

    public function test_that_ends_with_returns_false_for_non_matching_suffix(): void
    {
        self::assertFalse(Validate::endsWith('hello world', 'hello'));
        self::assertFalse(Validate::endsWith([], 'foo'));
    }

    public function test_that_exact_length_returns_true_for_matching_length(): void
    {
        self::assertTrue(Validate::exactLength('hello', 5));
        self::assertTrue(Validate::exactLength('', 0));
    }

    public function test_that_exact_length_returns_false_for_non_matching_length(): void
    {
        self::assertFalse(Validate::exactLength('hello', 4));
        self::assertFalse(Validate::exactLength([], 0));
    }

    public function test_that_min_length_returns_true_for_sufficient_length(): void
    {
        self::assertTrue(Validate::minLength('hello', 3));
        self::assertTrue(Validate::minLength('hello', 5));
    }

    public function test_that_min_length_returns_false_for_insufficient_length(): void
    {
        self::assertFalse(Validate::minLength('hi', 5));
        self::assertFalse(Validate::minLength([], 0));
    }

    public function test_that_max_length_returns_true_for_within_max(): void
    {
        self::assertTrue(Validate::maxLength('hi', 5));
        self::assertTrue(Validate::maxLength('hello', 5));
    }

    public function test_that_max_length_returns_false_for_exceeding_max(): void
    {
        self::assertFalse(Validate::maxLength('hello world', 5));
        self::assertFalse(Validate::maxLength([], 0));
    }

    public function test_that_range_length_returns_true_for_value_within_range(): void
    {
        self::assertTrue(Validate::rangeLength('hello', 3, 7));
        self::assertTrue(Validate::rangeLength('hi', 2, 5));
    }

    public function test_that_range_length_returns_false_for_value_outside_range(): void
    {
        self::assertFalse(Validate::rangeLength('hi', 3, 7));
        self::assertFalse(Validate::rangeLength('hello world', 3, 7));
        self::assertFalse(Validate::rangeLength([], 0, 5));
    }

    // -------------------------------------------------------------------------
    // Numeric checks
    // -------------------------------------------------------------------------

    public function test_that_exact_number_returns_true_for_matching_number(): void
    {
        self::assertTrue(Validate::exactNumber(42, 42));
        self::assertTrue(Validate::exactNumber('42', 42));
        self::assertTrue(Validate::exactNumber(3.14, 3.14));
    }

    public function test_that_exact_number_returns_false_for_non_matching_number(): void
    {
        self::assertFalse(Validate::exactNumber(41, 42));
        self::assertFalse(Validate::exactNumber('abc', 42));
    }

    public function test_that_min_number_returns_true_for_value_at_or_above_minimum(): void
    {
        self::assertTrue(Validate::minNumber(5, 3));
        self::assertTrue(Validate::minNumber(3, 3));
        self::assertTrue(Validate::minNumber('10', 5));
    }

    public function test_that_min_number_returns_false_for_value_below_minimum(): void
    {
        self::assertFalse(Validate::minNumber(2, 3));
        self::assertFalse(Validate::minNumber('abc', 0));
    }

    public function test_that_max_number_returns_true_for_value_at_or_below_maximum(): void
    {
        self::assertTrue(Validate::maxNumber(3, 5));
        self::assertTrue(Validate::maxNumber(5, 5));
    }

    public function test_that_max_number_returns_false_for_value_above_maximum(): void
    {
        self::assertFalse(Validate::maxNumber(6, 5));
        self::assertFalse(Validate::maxNumber('abc', 10));
    }

    public function test_that_range_number_returns_true_for_value_within_range(): void
    {
        self::assertTrue(Validate::rangeNumber(5, 1, 10));
        self::assertTrue(Validate::rangeNumber(1, 1, 10));
        self::assertTrue(Validate::rangeNumber(10, 1, 10));
    }

    public function test_that_range_number_returns_false_for_value_outside_range(): void
    {
        self::assertFalse(Validate::rangeNumber(0, 1, 10));
        self::assertFalse(Validate::rangeNumber(11, 1, 10));
        self::assertFalse(Validate::rangeNumber('abc', 1, 10));
    }

    public function test_that_whole_number_returns_true_for_non_negative_integers(): void
    {
        self::assertTrue(Validate::wholeNumber(0));
        self::assertTrue(Validate::wholeNumber(5));
        self::assertTrue(Validate::wholeNumber('10'));
    }

    public function test_that_whole_number_returns_false_for_negative_integers(): void
    {
        self::assertFalse(Validate::wholeNumber(-1));
        self::assertFalse(Validate::wholeNumber(3.14));
        self::assertFalse(Validate::wholeNumber('abc'));
    }

    public function test_that_natural_number_returns_true_for_positive_integers(): void
    {
        self::assertTrue(Validate::naturalNumber(1));
        self::assertTrue(Validate::naturalNumber(100));
        self::assertTrue(Validate::naturalNumber('5'));
    }

    public function test_that_natural_number_returns_false_for_zero_and_negative(): void
    {
        self::assertFalse(Validate::naturalNumber(0));
        self::assertFalse(Validate::naturalNumber(-1));
        self::assertFalse(Validate::naturalNumber(3.14));
    }

    public function test_that_int_value_returns_true_for_integer_representable_values(): void
    {
        self::assertTrue(Validate::intValue(42));
        self::assertTrue(Validate::intValue('42'));
        self::assertTrue(Validate::intValue(5.0));
    }

    public function test_that_int_value_returns_false_for_non_integer_values(): void
    {
        self::assertFalse(Validate::intValue(3.14));
        self::assertFalse(Validate::intValue('abc'));
    }

    // -------------------------------------------------------------------------
    // Collection checks
    // -------------------------------------------------------------------------

    public function test_that_exact_count_returns_true_for_matching_count(): void
    {
        self::assertTrue(Validate::exactCount([1, 2, 3], 3));
        self::assertTrue(Validate::exactCount([], 0));
    }

    public function test_that_exact_count_returns_false_for_non_matching_count(): void
    {
        self::assertFalse(Validate::exactCount([1, 2], 3));
        self::assertFalse(Validate::exactCount('string', 6));
    }

    public function test_that_min_count_returns_true_for_sufficient_count(): void
    {
        self::assertTrue(Validate::minCount([1, 2, 3], 3));
        self::assertTrue(Validate::minCount([1, 2, 3, 4], 3));
    }

    public function test_that_min_count_returns_false_for_insufficient_count(): void
    {
        self::assertFalse(Validate::minCount([1, 2], 3));
        self::assertFalse(Validate::minCount('not-countable', 1));
    }

    public function test_that_max_count_returns_true_for_within_max_count(): void
    {
        self::assertTrue(Validate::maxCount([1, 2], 3));
        self::assertTrue(Validate::maxCount([1, 2, 3], 3));
    }

    public function test_that_max_count_returns_false_for_exceeding_max_count(): void
    {
        self::assertFalse(Validate::maxCount([1, 2, 3, 4], 3));
        self::assertFalse(Validate::maxCount('not-countable', 10));
    }

    public function test_that_range_count_returns_true_for_count_within_range(): void
    {
        self::assertTrue(Validate::rangeCount([1, 2, 3], 2, 5));
        self::assertTrue(Validate::rangeCount([1, 2], 2, 5));
    }

    public function test_that_range_count_returns_false_for_count_outside_range(): void
    {
        self::assertFalse(Validate::rangeCount([1], 2, 5));
        self::assertFalse(Validate::rangeCount([1, 2, 3, 4, 5, 6], 2, 5));
        self::assertFalse(Validate::rangeCount('not-countable', 0, 10));
    }

    // -------------------------------------------------------------------------
    // Object/Type checks
    // -------------------------------------------------------------------------

    public function test_that_is_one_of_returns_true_when_value_is_in_set(): void
    {
        self::assertTrue(Validate::isOneOf('b', ['a', 'b', 'c']));
        self::assertTrue(Validate::isOneOf(2, [1, 2, 3]));
    }

    public function test_that_is_one_of_returns_false_when_value_is_not_in_set(): void
    {
        self::assertFalse(Validate::isOneOf('d', ['a', 'b', 'c']));
        self::assertFalse(Validate::isOneOf('1', [1, 2, 3]));
    }

    public function test_that_key_isset_returns_true_for_existing_key(): void
    {
        self::assertTrue(Validate::keyIsset(['key' => 'value'], 'key'));
        self::assertTrue(Validate::keyIsset(['key' => 0], 'key'));
    }

    public function test_that_key_isset_returns_false_for_missing_key(): void
    {
        self::assertFalse(Validate::keyIsset(['key' => 'value'], 'missing'));
        self::assertFalse(Validate::keyIsset(['key' => null], 'key'));
        self::assertFalse(Validate::keyIsset('not-array', 'key'));
    }

    public function test_that_key_not_empty_returns_true_for_non_empty_key(): void
    {
        self::assertTrue(Validate::keyNotEmpty(['key' => 'value'], 'key'));
        self::assertTrue(Validate::keyNotEmpty(['key' => 1], 'key'));
    }

    public function test_that_key_not_empty_returns_false_for_empty_or_missing_key(): void
    {
        self::assertFalse(Validate::keyNotEmpty(['key' => ''], 'key'));
        self::assertFalse(Validate::keyNotEmpty(['key' => 0], 'key'));
        self::assertFalse(Validate::keyNotEmpty([], 'key'));
        self::assertFalse(Validate::keyNotEmpty('not-array', 'key'));
    }

    public function test_that_are_equal_returns_true_for_equal_values(): void
    {
        self::assertTrue(Validate::areEqual(1, 1));
        self::assertTrue(Validate::areEqual('hello', 'hello'));
    }

    public function test_that_are_equal_returns_true_for_equal_equatable_objects(): void
    {
        $type1 = Type::create(RuntimeException::class);
        $type2 = Type::create(RuntimeException::class);

        self::assertTrue(Validate::areEqual($type1, $type2));
    }

    public function test_that_are_equal_returns_false_for_not_equal_values(): void
    {
        self::assertFalse(Validate::areEqual(1, 2));
        self::assertFalse(Validate::areEqual('hello', 'world'));
    }

    public function test_that_are_not_equal_returns_true_for_different_values(): void
    {
        self::assertTrue(Validate::areNotEqual(1, 2));
        self::assertTrue(Validate::areNotEqual('hello', 'world'));
    }

    public function test_that_are_not_equal_returns_true_for_unequal_equatable_objects(): void
    {
        $type1 = Type::create(RuntimeException::class);
        $type2 = Type::create(\Exception::class);

        self::assertTrue(Validate::areNotEqual($type1, $type2));
    }

    public function test_that_are_not_equal_returns_false_for_equal_equatable_objects(): void
    {
        $type1 = Type::create(RuntimeException::class);
        $type2 = Type::create(RuntimeException::class);

        self::assertFalse(Validate::areNotEqual($type1, $type2));
    }

    public function test_that_are_same_returns_true_for_identical_values(): void
    {
        $object = new stdClass();

        self::assertTrue(Validate::areSame($object, $object));
        self::assertTrue(Validate::areSame(42, 42));
        self::assertTrue(Validate::areSame('hello', 'hello'));
    }

    public function test_that_are_same_returns_false_for_different_values(): void
    {
        self::assertFalse(Validate::areSame(new stdClass(), new stdClass()));
        self::assertFalse(Validate::areSame(1, '1'));
    }

    public function test_that_are_not_same_returns_true_for_different_values(): void
    {
        self::assertTrue(Validate::areNotSame(1, '1'));
        self::assertTrue(Validate::areNotSame(new stdClass(), new stdClass()));
    }

    public function test_that_are_same_type_returns_true_for_same_object_types(): void
    {
        $ex1 = new RuntimeException('a');
        $ex2 = new RuntimeException('b');

        self::assertTrue(Validate::areSameType($ex1, $ex2));
    }

    public function test_that_are_same_type_returns_true_for_same_scalar_types(): void
    {
        self::assertTrue(Validate::areSameType(1, 2));
        self::assertTrue(Validate::areSameType('a', 'b'));
    }

    public function test_that_are_same_type_returns_false_for_different_types(): void
    {
        self::assertFalse(Validate::areSameType(1, '1'));
        self::assertFalse(Validate::areSameType(new stdClass(), new RuntimeException('test')));
    }

    public function test_that_is_type_returns_true_for_null_type(): void
    {
        self::assertTrue(Validate::isType('anything', null));
        self::assertTrue(Validate::isType(42, null));
    }

    public function test_that_is_type_returns_true_for_matching_simple_type(): void
    {
        self::assertTrue(Validate::isType('hello', 'string'));
        self::assertTrue(Validate::isType(42, 'int'));
        self::assertTrue(Validate::isType([], 'array'));
        self::assertTrue(Validate::isType(new stdClass(), 'object'));
        self::assertTrue(Validate::isType(true, 'bool'));
        self::assertTrue(Validate::isType(3.14, 'float'));
        self::assertTrue(Validate::isType(function () {}, 'callable'));
    }

    public function test_that_is_type_returns_true_for_matching_class(): void
    {
        $exception = new RuntimeException('test');

        self::assertTrue(Validate::isType($exception, RuntimeException::class));
        self::assertTrue(Validate::isType($exception, \Exception::class));
    }

    public function test_that_is_type_returns_false_for_non_matching_type(): void
    {
        self::assertFalse(Validate::isType('hello', 'int'));
        self::assertFalse(Validate::isType(42, 'string'));
    }

    public function test_that_is_list_of_returns_true_for_typed_list(): void
    {
        self::assertTrue(Validate::isListOf(['hello', 'world'], 'string'));
        self::assertTrue(Validate::isListOf([1, 2, 3], 'int'));
    }

    public function test_that_is_list_of_returns_false_for_mixed_type_list(): void
    {
        self::assertFalse(Validate::isListOf(['hello', 42], 'string'));
        self::assertFalse(Validate::isListOf('not-array', 'string'));
    }

    public function test_that_is_list_of_returns_true_for_any_traversable_when_type_is_null(): void
    {
        self::assertTrue(Validate::isListOf(['a', 1, true], null));
    }

    public function test_that_is_string_castable_returns_true_for_castable_values(): void
    {
        self::assertTrue(Validate::isStringCastable('hello'));
        self::assertTrue(Validate::isStringCastable(42));
        self::assertTrue(Validate::isStringCastable(3.14));
        self::assertTrue(Validate::isStringCastable(true));
        self::assertTrue(Validate::isStringCastable(null));
    }

    public function test_that_is_string_castable_returns_true_for_stringable_object(): void
    {
        $object = new class implements Stringable {
            public function __toString(): string
            {
                return 'value';
            }
        };

        self::assertTrue(Validate::isStringCastable($object));
    }

    public function test_that_is_string_castable_returns_false_for_non_castable_values(): void
    {
        self::assertFalse(Validate::isStringCastable([]));
        self::assertFalse(Validate::isStringCastable(new stdClass()));
    }

    public function test_that_is_json_encodable_returns_true_for_encodable_values(): void
    {
        self::assertTrue(Validate::isJsonEncodable(['key' => 'value']));
        self::assertTrue(Validate::isJsonEncodable('string'));
        self::assertTrue(Validate::isJsonEncodable(42));
        self::assertTrue(Validate::isJsonEncodable(null));
    }

    public function test_that_is_json_encodable_returns_false_for_non_encodable_value(): void
    {
        // INF is not representable in JSON; json_encode returns false for it
        self::assertFalse(Validate::isJsonEncodable(INF));
    }

    public function test_that_is_traversable_returns_true_for_arrays_and_traversables(): void
    {
        self::assertTrue(Validate::isTraversable([]));
        self::assertTrue(Validate::isTraversable(new ArrayObject([1, 2, 3])));
    }

    public function test_that_is_traversable_returns_false_for_non_traversable(): void
    {
        self::assertFalse(Validate::isTraversable('string'));
        self::assertFalse(Validate::isTraversable(new stdClass()));
    }

    public function test_that_is_countable_returns_true_for_arrays_and_countables(): void
    {
        $countable = new class implements Countable {
            public function count(): int { return 3; }
        };

        self::assertTrue(Validate::isCountable([]));
        self::assertTrue(Validate::isCountable($countable));
    }

    public function test_that_is_countable_returns_false_for_non_countable(): void
    {
        self::assertFalse(Validate::isCountable('string'));
        self::assertFalse(Validate::isCountable(new stdClass()));
    }

    public function test_that_is_array_accessible_returns_true_for_arrays(): void
    {
        self::assertTrue(Validate::isArrayAccessible([]));
        self::assertTrue(Validate::isArrayAccessible(new ArrayObject()));
    }

    public function test_that_is_array_accessible_returns_false_for_non_accessible(): void
    {
        self::assertFalse(Validate::isArrayAccessible('string'));
        self::assertFalse(Validate::isArrayAccessible(new stdClass()));
    }

    public function test_that_is_comparable_returns_true_for_comparable_objects(): void
    {
        $str = StringObject::create('hello');

        self::assertTrue(Validate::isComparable($str));
    }

    public function test_that_is_comparable_returns_false_for_non_comparable(): void
    {
        self::assertFalse(Validate::isComparable('string'));
        self::assertFalse(Validate::isComparable(new stdClass()));
    }

    public function test_that_is_equatable_returns_true_for_equatable_objects(): void
    {
        $type = Type::create(RuntimeException::class);

        self::assertTrue(Validate::isEquatable($type));
    }

    public function test_that_is_equatable_returns_false_for_non_equatable(): void
    {
        self::assertFalse(Validate::isEquatable('string'));
        self::assertFalse(Validate::isEquatable(new stdClass()));
    }

    public function test_that_implements_interface_returns_true_for_implementing_class(): void
    {
        $type = Type::create(RuntimeException::class);

        self::assertTrue(Validate::implementsInterface($type, Equatable::class));
        self::assertTrue(Validate::implementsInterface(Type::class, Equatable::class));
    }

    public function test_that_implements_interface_returns_false_for_non_implementing_class(): void
    {
        self::assertFalse(Validate::implementsInterface(new stdClass(), Equatable::class));
        self::assertFalse(Validate::implementsInterface('NonExistentClass', Equatable::class));
    }

    public function test_that_is_instance_of_returns_true_for_matching_class(): void
    {
        $exception = new RuntimeException('test');

        self::assertTrue(Validate::isInstanceOf($exception, RuntimeException::class));
        self::assertTrue(Validate::isInstanceOf($exception, \Exception::class));
    }

    public function test_that_is_instance_of_returns_false_for_non_matching_class(): void
    {
        self::assertFalse(Validate::isInstanceOf(new stdClass(), RuntimeException::class));
    }

    public function test_that_is_subclass_of_returns_true_for_subclass(): void
    {
        self::assertTrue(Validate::isSubclassOf(new RuntimeException('test'), \Exception::class));
    }

    public function test_that_is_subclass_of_returns_false_for_same_class(): void
    {
        self::assertFalse(Validate::isSubclassOf(RuntimeException::class, RuntimeException::class));
    }

    public function test_that_class_exists_returns_true_for_existing_class(): void
    {
        self::assertTrue(Validate::classExists(RuntimeException::class));
        self::assertTrue(Validate::classExists(stdClass::class));
    }

    public function test_that_class_exists_returns_false_for_non_existing_class(): void
    {
        self::assertFalse(Validate::classExists('NonExistentClass'));
        self::assertFalse(Validate::classExists([]));
    }

    public function test_that_interface_exists_returns_true_for_existing_interface(): void
    {
        self::assertTrue(Validate::interfaceExists(Equatable::class));
        self::assertTrue(Validate::interfaceExists(Stringable::class));
    }

    public function test_that_interface_exists_returns_false_for_non_existing_interface(): void
    {
        self::assertFalse(Validate::interfaceExists('NonExistentInterface'));
        self::assertFalse(Validate::interfaceExists([]));
    }

    public function test_that_method_exists_returns_true_for_existing_method(): void
    {
        $exception = new RuntimeException('test');

        self::assertTrue(Validate::methodExists('getMessage', $exception));
        self::assertTrue(Validate::methodExists('getMessage', RuntimeException::class));
    }

    public function test_that_method_exists_returns_false_for_non_existing_method(): void
    {
        self::assertFalse(Validate::methodExists('nonExistentMethod', new stdClass()));
        self::assertFalse(Validate::methodExists([], new stdClass()));
    }

    // -------------------------------------------------------------------------
    // Filesystem checks
    // -------------------------------------------------------------------------

    public function test_that_is_path_returns_true_for_existing_path(): void
    {
        self::assertTrue(Validate::isPath(__FILE__));
        self::assertTrue(Validate::isPath(__DIR__));
    }

    public function test_that_is_path_returns_false_for_non_existing_path(): void
    {
        self::assertFalse(Validate::isPath('/tmp/validate_test_nonexistent_path_xyz'));
    }

    public function test_that_is_path_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isPath([]));
    }

    public function test_that_is_file_returns_true_for_existing_file(): void
    {
        self::assertTrue(Validate::isFile(__FILE__));
    }

    public function test_that_is_file_returns_false_for_directory(): void
    {
        self::assertFalse(Validate::isFile(__DIR__));
    }

    public function test_that_is_file_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isFile([]));
    }

    public function test_that_is_dir_returns_true_for_existing_directory(): void
    {
        self::assertTrue(Validate::isDir(__DIR__));
    }

    public function test_that_is_dir_returns_false_for_file(): void
    {
        self::assertFalse(Validate::isDir(__FILE__));
    }

    public function test_that_is_dir_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isDir([]));
    }

    public function test_that_is_readable_returns_true_for_readable_path(): void
    {
        self::assertTrue(Validate::isReadable(__FILE__));
    }

    public function test_that_is_readable_returns_false_for_non_existing_path(): void
    {
        self::assertFalse(Validate::isReadable('/tmp/validate_test_nonexistent_path_xyz'));
    }

    public function test_that_is_readable_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isReadable([]));
    }

    public function test_that_is_writable_returns_true_for_writable_path(): void
    {
        self::assertTrue(Validate::isWritable(sys_get_temp_dir()));
    }

    public function test_that_is_writable_returns_false_for_non_existing_path(): void
    {
        self::assertFalse(Validate::isWritable('/tmp/validate_test_nonexistent_path_xyz'));
    }

    public function test_that_is_writable_returns_false_for_non_string_castable_value(): void
    {
        self::assertFalse(Validate::isWritable([]));
    }
}
