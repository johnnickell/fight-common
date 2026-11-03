<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture;

final class ValidationRuleExamples
{
    /**
     * Returns independently expected passing and failing inputs for supported registrations
     */
    public static function cases(): iterable
    {
        // Type, rule text, string arguments, passing input, failing input.
        yield 'alpha' => ['Alpha', 'alpha', [], ['value' => 'Alice'], ['value' => '123']];
        yield 'alpha dash' => ['AlphaDash', 'alpha_dash', [], ['value' => 'a-b_c'], ['value' => '!']];
        yield 'alpha num' => ['AlphaNum', 'alpha_num', [], ['value' => 'a1'], ['value' => '!']];
        yield 'alpha num dash' => ['AlphaNumDash', 'alpha_num_dash', [], ['value' => 'a1-b_c'], ['value' => '!']];
        yield 'blank' => ['Blank', 'blank', [], ['value' => '  '], ['value' => 'a']];
        yield 'contains' => ['Contains', 'contains[ell]', ['ell'], ['value' => 'hello'], ['value' => 'world']];
        yield 'date' => ['Date', 'date[Y-m-d]', ['Y-m-d'], ['value' => '2024-02-29'], ['value' => '2024-02-30']];
        yield 'datetime' => ['DateTime', 'date_time[Y-m-d H:i:s]', ['Y-m-d H:i:s'], ['value' => '2024-02-29 12:30:00'], ['value' => 'bad']];
        yield 'digits' => ['Digits', 'digits', [], ['value' => '0123'], ['value' => '12a']];
        yield 'email' => ['Email', 'email', [], ['value' => 'user@example.com'], ['value' => 'bad']];
        yield 'empty' => ['Empty', 'empty', [], ['value' => '0'], ['value' => 'a']];
        yield 'ends with' => ['EndsWith', 'ends_with[lo]', ['lo'], ['value' => 'hello'], ['value' => 'world']];
        yield 'equals' => ['Equals', 'equals[other]', ['other'], ['value' => 2, 'other' => '2'], ['value' => 2, 'other' => 3]];
        yield 'exact count coerces decimal' => ['ExactCount', 'exact_count[2.9]', ['2.9'], ['value' => ['a', 'b']], ['value' => []]];
        yield 'exact length coerces decimal' => ['ExactLength', 'exact_length[2.9]', ['2.9'], ['value' => 'ab'], ['value' => 'abc']];
        yield 'exact integer' => ['ExactNumber', 'exact_number[2]', ['2'], ['value' => 2], ['value' => 3]];
        yield 'exact float' => ['ExactNumber', 'exact_number[2.5]', ['2.5'], ['value' => 2.5], ['value' => 2]];
        yield 'exact exponent' => ['ExactNumber', 'exact_number[2e1]', ['2e1'], ['value' => 20], ['value' => 2]];
        yield 'exact nonnumeric conversion' => ['ExactNumber', 'exact_number[bad]', ['bad'], ['value' => 0], ['value' => 1]];
        yield 'false' => ['False', 'false', [], ['value' => false], ['value' => 0]];
        yield 'falsy' => ['Falsy', 'falsy', [], ['value' => 0], ['value' => 1]];
        yield 'list' => ['InList', 'in_list[red, blue]', ['red', 'blue'], ['value' => 'blue'], ['value' => 'green']];
        yield 'empty list' => ['InList', 'in_list', [], [], ['value' => 'red']];
        yield 'ip' => ['IpAddress', 'ip_address', [], ['value' => '2001:db8::1'], ['value' => 'bad']];
        yield 'ipv4' => ['IpV4Address', 'ip_v4_address', [], ['value' => '192.0.2.1'], ['value' => '2001:db8::1']];
        yield 'ipv6' => ['IpV6Address', 'ip_v6_address', [], ['value' => '2001:db8::1'], ['value' => '192.0.2.1']];
        yield 'json' => ['Json', 'json', [], ['value' => '{"a":1}'], ['value' => '{']];
        yield 'key isset rejects null' => ['KeyIsset', 'key_isset[key]', ['key'], ['value' => ['key' => 0]], ['value' => ['key' => null]]];
        yield 'key not empty' => ['KeyNotEmpty', 'key_not_empty[key]', ['key'], ['value' => ['key' => 1]], ['value' => ['key' => 0]]];
        yield 'list of' => ['ListOf', 'list_of[string]', ['string'], ['value' => ['a', 'b']], ['value' => ['a', 1]]];
        yield 'regex alternation' => ['Match', 'match[/^(red|blue)$/]', ['/^(red|blue)$/'], ['value' => 'blue'], ['value' => 'green']];
        yield 'max count' => ['MaxCount', 'max_count[2.9]', ['2.9'], ['value' => [1, 2]], ['value' => [1, 2, 3]]];
        yield 'max length' => ['MaxLength', 'max_length[2.9]', ['2.9'], ['value' => 'ab'], ['value' => 'abc']];
        yield 'max integer' => ['MaxNumber', 'max_number[2]', ['2'], ['value' => 2], ['value' => 3]];
        yield 'max float' => ['MaxNumber', 'max_number[2.5]', ['2.5'], ['value' => 2.5], ['value' => 3]];
        yield 'min count' => ['MinCount', 'min_count[2.9]', ['2.9'], ['value' => [1, 2]], ['value' => [1]]];
        yield 'min length' => ['MinLength', 'min_length[2.9]', ['2.9'], ['value' => 'ab'], ['value' => 'a']];
        yield 'min integer' => ['MinNumber', 'min_number[2]', ['2'], ['value' => 2], ['value' => 1]];
        yield 'min float' => ['MinNumber', 'min_number[2.5]', ['2.5'], ['value' => 2.5], ['value' => 2]];
        yield 'natural' => ['NaturalNumber', 'natural_number', [], ['value' => 1], ['value' => 0]];
        yield 'not blank' => ['NotBlank', 'not_blank', [], ['value' => 'a'], ['value' => '  ']];
        yield 'not empty' => ['NotEmpty', 'not_empty', [], ['value' => 'a'], ['value' => '0']];
        yield 'not equals' => ['NotEquals', 'not_equals[other]', ['other'], ['value' => 2, 'other' => 3], ['value' => 2, 'other' => '2']];
        yield 'not null' => ['NotNull', 'not_null', [], ['value' => 0], ['value' => null]];
        yield 'not same' => ['NotSame', 'not_same[other]', ['other'], ['value' => 2, 'other' => '2'], ['value' => 2, 'other' => 2]];
        yield 'not scalar' => ['NotScalar', 'not_scalar', [], ['value' => []], ['value' => 'a']];
        yield 'null' => ['Null', 'null', [], ['value' => null], ['value' => 0]];
        yield 'numeric' => ['Numeric', 'numeric', [], ['value' => '2.5'], ['value' => 'a']];
        yield 'range count' => ['RangeCount', 'range_count[1.9, 2.9]', ['1.9', '2.9'], ['value' => [1, 2]], ['value' => []]];
        yield 'range length' => ['RangeLength', 'range_length[1.9, 2.9]', ['1.9', '2.9'], ['value' => 'ab'], ['value' => 'abc']];
        yield 'range integer' => ['RangeNumber', 'range_number[1, 2]', ['1', '2'], ['value' => 2], ['value' => 3]];
        yield 'range float' => ['RangeNumber', 'range_number[1.5, 2.5]', ['1.5', '2.5'], ['value' => 2.5], ['value' => 3]];
        yield 'range mixed' => ['RangeNumber', 'range_number[1, 2.5]', ['1', '2.5'], ['value' => 1], ['value' => 0.5]];
        yield 'required permits null' => ['Required', 'required', [], ['value' => null], []];
        yield 'same' => ['Same', 'same[other]', ['other'], ['value' => 2, 'other' => 2], ['value' => 2, 'other' => '2']];
        yield 'scalar' => ['Scalar', 'scalar', [], ['value' => 0], ['value' => []]];
        yield 'starts with' => ['StartsWith', 'starts_with[he]', ['he'], ['value' => 'hello'], ['value' => 'world']];
        yield 'time' => ['Time', 'time[H:i:s]', ['H:i:s'], ['value' => '12:30:00'], ['value' => '25:00:00']];
        yield 'timezone' => ['Timezone', 'timezone', [], ['value' => 'UTC'], ['value' => 'bad']];
        yield 'true' => ['True', 'true', [], ['value' => true], ['value' => 1]];
        yield 'truthy' => ['Truthy', 'truthy', [], ['value' => 1], ['value' => 0]];
        yield 'type' => ['Type', 'type[string]', ['string'], ['value' => '2'], ['value' => 2]];
        yield 'uri' => ['Uri', 'uri', [], ['value' => 'https://example.com/path'], ['value' => 'not a uri']];
        yield 'urn' => ['Urn', 'urn', [], ['value' => 'urn:isbn:0451450523'], ['value' => 'not a urn']];
        yield 'uuid' => ['Uuid', 'uuid', [], ['value' => '550e8400-e29b-41d4-a716-446655440000'], ['value' => 'bad']];
        yield 'whole' => ['WholeNumber', 'whole_number', [], ['value' => 0], ['value' => -1]];
    }
}
