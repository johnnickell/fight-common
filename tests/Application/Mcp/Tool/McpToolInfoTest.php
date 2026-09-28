<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolJson;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\McpToolSchema;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

#[CoversClass(McpToolInfo::class)]
#[CoversClass(McpToolSchema::class)]
#[CoversClass(McpToolJson::class)]
#[CoversClass(McpToolOutput::class)]
final class McpToolInfoTest extends UnitTestCase
{
    public function test_that_metadata_preserves_declared_schemas_and_isolates_mutable_objects(): void
    {
        $property = (object) ['type' => 'string', 'description' => 'A public identifier'];
        $input = ['type' => 'object', 'properties' => ['id' => $property], 'required' => ['id']];
        $output = ['type' => ['string', 'null']];
        $info = new McpToolInfo('orders.find', 'Find an order', $input, $output);
        $property->description = 'Changed';
        $info->inputSchema()->properties->id->type = 'number';
        $info->outputSchema()->type = 'boolean';

        self::assertSame('orders.find', $info->name());
        self::assertSame('Find an order', $info->description());
        self::assertSame('string', $info->inputSchema()->properties->id->type);
        self::assertSame('A public identifier', $info->inputSchema()->properties->id->description);
        self::assertEquals((object) $output, $info->outputSchema());
        self::assertEquals([
            'name' => 'orders.find',
            'description' => 'Find an order',
            'inputSchema' => $info->inputSchema(),
            'outputSchema' => (object) $output,
        ], $info->toArray());
    }

    public function test_that_schema_profile_preserves_supported_keywords_and_empty_object_maps(): void
    {
        $schema = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            '$defs' => ['unused' => false],
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 0, 'maxLength' => 20, 'x-mcp-header' => 'Name'],
                'count' => ['type' => 'integer', 'minimum' => -1, 'maximum' => 100,
                    'exclusiveMinimum' => -2.5, 'exclusiveMaximum' => 101.5, 'multipleOf' => 1],
                'items' => ['type' => 'array', 'items' => true, 'minItems' => 0, 'maxItems' => 3, 'uniqueItems' => true],
                'open' => ['properties' => [], '$defs' => []],
            ],
            'minProperties' => 0,
            'maxProperties' => 5,
            'additionalProperties' => false,
            'required' => [],
            'allOf' => [true, new stdClass()],
            'anyOf' => [['type' => 'object'], false],
            'oneOf' => [new stdClass()],
            'not' => false,
            'enum' => [(object) ['name' => 'safe'], null],
            'const' => new stdClass(),
            'default' => new stdClass(),
            'examples' => [new stdClass()],
            'title' => 'Example',
            'description' => 'Schema description',
            'readOnly' => true,
            'writeOnly' => false,
            'deprecated' => false,
        ];
        $info = new McpToolInfo('A_1.-', 'Public schema', $schema, []);
        $expected = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $expected->properties->open->properties = new stdClass();
        $expected->properties->open->{'$defs'} = new stdClass();

        self::assertEquals($expected, $info->inputSchema());
        self::assertEquals(new stdClass(), $info->outputSchema());
        self::assertSame('{}', json_encode($info->outputSchema(), JSON_THROW_ON_ERROR));
    }

    #[DataProvider('invalidMetadata')]
    public function test_that_invalid_metadata_fails_composition(string $name, string $description): void
    {
        $this->expectException(DomainException::class);
        new McpToolInfo($name, $description, ['type' => 'object'], []);
    }

    public static function invalidMetadata(): iterable
    {
        yield ['', 'Description'];
        yield ['bad name', 'Description'];
        yield ['bad/name', 'Description'];
        yield ["name\n", 'Description'];
        yield ['é', 'Description'];
        yield [str_repeat('a', 129), 'Description'];
        yield ['valid', ''];
        yield ['valid', " \t\n"];
        yield ['valid', "\xFF"];
    }

    #[DataProvider('invalidSchemas')]
    public function test_that_invalid_or_unsupported_schema_declarations_fail_composition(array $schema): void
    {
        $this->expectException(DomainException::class);
        new McpToolInfo('valid', 'Description', ['type' => 'object'], $schema);
    }

    public static function invalidSchemas(): iterable
    {
        yield 'list root' => [[true]];
        yield 'unsupported dialect' => [['$schema' => 'http://json-schema.org/draft-07/schema#']];
        yield 'unsupported reference' => [['$ref' => 'https://example.test/schema']];
        yield 'unsupported pattern' => [['pattern' => '[a-z]+']];
        yield 'unsupported extension' => [['x-other' => true]];
        yield 'numeric keyword' => [[2 => true]];
        yield 'unknown type' => [['type' => 'date']];
        yield 'empty type list' => [['type' => []]];
        yield 'duplicate type' => [['type' => ['string', 'string']]];
        yield 'invalid type item' => [['type' => ['string', 1]]];
        yield 'unknown type item' => [['type' => ['string', 'date']]];
        yield 'map of types' => [['type' => ['a' => 'string']]];
        yield 'scalar properties' => [['properties' => 1]];
        yield 'list properties' => [['properties' => [true]]];
        yield 'invalid child' => [['properties' => ['id' => 'string']]];
        yield 'invalid nested keyword' => [['properties' => ['id' => ['type' => 'date']]]];
        yield 'invalid items' => [['items' => []]];
        yield 'tuple items' => [['items' => [['type' => 'string']]]];
        yield 'invalid additional properties' => [['additionalProperties' => 1]];
        yield 'invalid required' => [['required' => [1]]];
        yield 'duplicate required' => [['required' => ['id', 'id']]];
        yield 'object required' => [['required' => ['id' => 'id']]];
        yield 'empty enum' => [['enum' => []]];
        yield 'object enum' => [['enum' => new stdClass()]];
        yield 'invalid examples' => [['examples' => new stdClass()]];
        yield 'invalid title' => [['title' => 1]];
        yield 'invalid description' => [['description' => false]];
        yield 'negative count' => [['minLength' => -1]];
        yield 'fractional count' => [['maxItems' => 1.5]];
        yield 'string bound' => [['maximum' => '5']];
        yield 'zero multiple' => [['multipleOf' => 0]];
        yield 'negative multiple' => [['multipleOf' => -1]];
        yield 'invalid boolean keyword' => [['uniqueItems' => 1]];
        yield 'empty composition' => [['allOf' => []]];
        yield 'scalar composition' => [['oneOf' => true]];
        yield 'invalid composition child' => [['anyOf' => [1]]];
        yield 'invalid header' => [['x-mcp-header' => 'bad value']];
        yield 'invalid unicode key' => [["\xFF" => true]];
    }

    #[DataProvider('invalidInputSchemas')]
    public function test_that_input_schema_requires_an_object_root(array $schema): void
    {
        $this->expectException(DomainException::class);
        new McpToolInfo('valid', 'Description', $schema, []);
    }

    public static function invalidInputSchemas(): iterable
    {
        yield [[]];
        yield [['type' => 'string']];
        yield [['type' => ['object']]];
    }

    public function test_that_output_is_complete_json_with_matching_text_and_no_protocol_envelope(): void
    {
        foreach ([null, true, 1, 1.5, 'hello', [1, 2], (object) ['id' => 'public']] as $content) {
            $output = McpToolOutput::structured($content);
            self::assertEquals($content, $output->structuredContent());
            self::assertEquals($content, json_decode($output->text(), false, 512, JSON_THROW_ON_ERROR));
        }

        $content = (object) ['nested' => (object) ['id' => 'public']];
        $output = McpToolOutput::structured($content);
        $content->nested->id = 'changed';
        $output->structuredContent()->nested->id = 'also changed';
        self::assertSame('{"nested":{"id":"public"}}', $output->text());
    }

    public function test_that_non_json_data_and_consumer_serializers_are_rejected_without_execution(): void
    {
        $serializer = new class implements JsonSerializable {
            public bool $called = false;
            public function jsonSerialize(): mixed
            {
                $this->called = true;
                return ['secret' => true];
            }
        };
        $resource = fopen('php://memory', 'r');
        try {
            foreach ([NAN, INF, $serializer, $resource, new class extends stdClass {}, "\xFF"] as $value) {
                try {
                    McpToolOutput::structured(['value' => $value]);
                    self::fail('Non-JSON data was accepted.');
                } catch (DomainException) {
                    self::assertFalse($serializer->called);
                }
            }
        } finally {
            fclose($resource);
        }
    }

    public function test_that_cyclic_data_rejects_without_unbounded_recursion(): void
    {
        $cycle = new stdClass();
        $cycle->self = $cycle;
        $this->expectException(DomainException::class);
        McpToolOutput::structured($cycle);
    }
}
