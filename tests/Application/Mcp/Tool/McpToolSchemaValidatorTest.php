<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\Tool\McpToolSchema;
use Fight\Common\Application\Mcp\Tool\McpToolSchemaValidator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpToolSchemaValidator::class)]
final class McpToolSchemaValidatorTest extends UnitTestCase
{
    #[DataProvider('conformance')]
    public function test_that_supported_schema_assertions_validate_json_values(array $schema, string $json, bool $valid): void
    {
        $declaration = json_decode(McpToolSchema::encode($schema, false), false, 512, JSON_THROW_ON_ERROR);
        $value = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        self::assertSame($valid, McpToolSchemaValidator::matches($value, $declaration));
    }

    public static function conformance(): iterable
    {
        yield 'empty schema' => [[], 'null', true];
        foreach (['object' => '{}', 'array' => '[]', 'string' => '"hello"', 'number' => '1.5', 'integer' => '1.0', 'boolean' => 'true', 'null' => 'null'] as $type => $json) {
            yield $type.' accepted' => [['type' => $type], $json, true];
            yield $type.' rejected' => [['type' => $type], $type === 'null' ? 'false' : 'null', false];
        }
        yield 'fraction not integer' => [['type' => 'integer'], '1.1', false];
        yield 'object not array' => [['type' => 'array'], '{}', false];
        yield 'array not object' => [['type' => 'object'], '[]', false];
        yield 'union' => [['type' => ['string', 'null']], 'null', true];
        yield 'union rejection' => [['type' => ['string', 'null']], 'false', false];
        yield 'const number equality' => [['const' => 1], '1.0', true];
        yield 'const no coercion' => [['const' => 1], '"1"', false];
        yield 'enum' => [['enum' => [null, 'allowed']], '"allowed"', true];
        yield 'enum rejection' => [['enum' => [null, 'allowed']], 'false', false];
        yield 'object key order' => [['const' => ['a' => 1, 'b' => [2, 3]]], '{"b":[2.0,3],"a":1.0}', true];
        yield 'missing object property' => [['const' => ['a' => 1]], '{"b":1}', false];
        yield 'different object count' => [['const' => ['a' => 1]], '{}', false];
        yield 'lists ordered' => [['const' => [1, 2]], '[2,1]', false];
        yield 'object distinct from list' => [['const' => (object) []], '[]', false];
        yield 'boolean distinct from number' => [['const' => false], '0', false];
        yield 'allOf' => [['allOf' => [['type' => 'integer'], ['minimum' => 2]]], '2', true];
        yield 'allOf rejection' => [['allOf' => [['type' => 'integer'], ['minimum' => 2]]], '1', false];
        yield 'anyOf' => [['anyOf' => [false, ['type' => 'string']]], '"x"', true];
        yield 'anyOf rejection' => [['anyOf' => [false, ['type' => 'string']]], '2', false];
        yield 'oneOf' => [['oneOf' => [['type' => 'integer'], ['type' => 'string']]], '2', true];
        yield 'oneOf ambiguous' => [['oneOf' => [true, ['type' => 'integer']]], '2', false];
        yield 'oneOf absent' => [['oneOf' => [false, ['type' => 'integer']]], 'null', false];
        yield 'not' => [['not' => ['type' => 'string']], 'false', true];
        yield 'not rejection' => [['not' => true], 'null', false];
        yield 'required nullable' => [['required' => ['a']], '{"a":null}', true];
        yield 'required missing' => [['required' => ['a']], '{}', false];
        yield 'properties optional' => [['properties' => ['a' => false]], '{}', true];
        yield 'property rejection' => [['properties' => ['a' => ['type' => 'integer']]], '{"a":"1"}', false];
        yield 'additional permitted' => [['properties' => ['a' => true]], '{"b":false}', true];
        yield 'additional rejected' => [['properties' => ['a' => true], 'additionalProperties' => false], '{"b":false}', false];
        yield 'declared property not additional' => [['properties' => ['a' => true], 'additionalProperties' => false], '{"a":false}', true];
        yield 'additional schema' => [['additionalProperties' => ['type' => 'boolean']], '{"a":false}', true];
        yield 'additional schema rejection' => [['additionalProperties' => ['type' => 'boolean']], '{"a":null}', false];
        yield 'numeric property' => [['properties' => (object) ['0' => (object) ['const' => 1]]], '{"0":1}', true];
        yield 'empty property map' => [['properties' => []], '{"x":1}', true];
        yield 'min properties' => [['minProperties' => 1], '{"a":null}', true];
        yield 'min properties rejection' => [['minProperties' => 1], '{}', false];
        yield 'max properties' => [['maxProperties' => 1], '{"a":null}', true];
        yield 'max properties rejection' => [['maxProperties' => 0], '{"a":null}', false];
        yield 'items' => [['items' => ['type' => 'integer']], '[1,2.0]', true];
        yield 'items rejection' => [['items' => false], '[1]', false];
        yield 'min items' => [['minItems' => 1], '[1]', true];
        yield 'min items rejection' => [['minItems' => 1], '[]', false];
        yield 'max items' => [['maxItems' => 1], '[1]', true];
        yield 'max items rejection' => [['maxItems' => 0], '[1]', false];
        yield 'unique' => [['uniqueItems' => true], '[true,1,"1",null,{},[]]', true];
        yield 'numeric duplicate' => [['uniqueItems' => true], '[1,1.0]', false];
        yield 'object duplicate' => [['uniqueItems' => true], '[{"a":1,"b":2},{"b":2,"a":1}]', false];
        yield 'unique disabled' => [['uniqueItems' => false], '[1,1]', true];
        yield 'unicode length' => [['minLength' => 2, 'maxLength' => 2], '"é😀"', true];
        yield 'length counts newline' => [['minLength' => 1, 'maxLength' => 1], '"\n"', true];
        yield 'minimum length rejection' => [['minLength' => 1], '""', false];
        yield 'maximum length rejection' => [['maxLength' => 1], '"ab"', false];
        yield 'inclusive minimum' => [['minimum' => 1.0], '1', true];
        yield 'minimum rejection' => [['minimum' => 1], '0', false];
        yield 'inclusive maximum' => [['maximum' => 1], '1.0', true];
        yield 'maximum rejection' => [['maximum' => 1], '2', false];
        yield 'exclusive minimum' => [['exclusiveMinimum' => 1], '2', true];
        yield 'exclusive minimum rejection' => [['exclusiveMinimum' => 1], '1', false];
        yield 'exclusive maximum' => [['exclusiveMaximum' => 1], '0', true];
        yield 'exclusive maximum rejection' => [['exclusiveMaximum' => 1], '1', false];
        yield 'negative ordering' => [['minimum' => -20, 'maximum' => -2], '-10', true];
        yield 'opposite sign minimum' => [['minimum' => -1], '0', true];
        yield 'opposite sign maximum' => [['maximum' => -1], '0', false];
        yield 'large exact integer' => [['const' => 9007199254740993], '9007199254740992.0', false];
        yield 'large exact bound' => [['minimum' => 9007199254740993], '9007199254740992.0', false];
        yield 'scientific small multiple' => [['multipleOf' => 1e-8], '0.00000003', true];
        yield 'decimal exact multiple' => [['multipleOf' => 0.1], '0.3', true];
        yield 'decimal not multiple' => [['multipleOf' => 0.1], '0.30000000000000004', false];
        yield 'small not multiple' => [['multipleOf' => 1], '1e-20', false];
        yield 'negative multiple' => [['multipleOf' => 3], '-12', true];
        yield 'zero multiple' => [['multipleOf' => 0.3], '0', true];
        yield 'borrow multiple' => [['multipleOf' => 17], '102', true];
        yield 'borrow not multiple' => [['multipleOf' => 17], '101', false];
        yield 'large multiple' => [['multipleOf' => 100], '1e300', true];
        yield 'minimum integer multiple' => [['multipleOf' => 2], '-9223372036854775808', true];
        yield 'negative zero equal' => [['const' => 0], '-0.0', true];
        yield 'annotations do not transform' => [['default' => 1, 'examples' => [2], '$defs' => ['ignored' => false], 'readOnly' => true], 'null', true];
        yield 'irrelevant keywords ignored' => [['minimum' => 1, 'minItems' => 1, 'minProperties' => 1, 'minLength' => 1], 'null', true];
    }
}
