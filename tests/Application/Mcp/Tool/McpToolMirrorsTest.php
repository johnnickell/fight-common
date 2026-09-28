<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\Tool\McpToolMirrors;
use Fight\Common\Application\Mcp\Tool\McpToolSchema;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpToolMirrors::class)]
final class McpToolMirrorsTest extends UnitTestCase
{
    public function test_that_only_exact_reachable_property_annotations_form_mirrors(): void
    {
        $schema = ['type' => 'object', 'properties' => [
            'account' => ['type' => 'object', 'properties' => [
                'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
                'count' => ['type' => ['integer', 'null'], 'x-mcp-header' => 'Count'],
                'enabled' => ['type' => 'boolean', 'x-mcp-header' => 'Enabled'],
            ]],
            'unannotated' => true,
        ], '$defs' => ['valid' => true], 'items' => false, 'not' => false, 'anyOf' => [true],
            'default' => ['x-mcp-header' => 'NotASchemaAnnotation']];
        $declarations = McpToolMirrors::declarations(McpToolSchema::create($schema, true));
        self::assertSame(['Region', 'Count', 'Enabled'], array_map(fn($declaration) => $declaration->headerName(), $declarations));
        foreach ($declarations as $index => $declaration) {
            self::assertSame('tools/call', $declaration->method());
            self::assertSame(['arguments', 'account', ['region', 'count', 'enabled'][$index]], $declaration->parameterPath());
        }
    }

    #[DataProvider('invalidAnnotations')]
    public function test_that_invalid_mirror_annotations_fail_composition(array $schema): void
    {
        $this->expectException(DomainException::class);
        McpToolMirrors::declarations(McpToolSchema::create(['type' => 'object', ...$schema], true));
    }

    public static function invalidAnnotations(): iterable
    {
        yield 'root' => [['x-mcp-header' => 'Root']];
        foreach (['number', 'object', 'array', 'null'] as $type) {
            yield $type => [['properties' => ['value' => ['type' => $type, 'x-mcp-header' => 'Value']]]];
        }
        yield 'no type' => [['properties' => ['value' => ['x-mcp-header' => 'Value']]]];
        yield 'empty path segment' => [['properties' => ['' => ['type' => 'string', 'x-mcp-header' => 'Value']]]];
        yield 'collision' => [['properties' => [
            'first' => ['type' => 'string', 'x-mcp-header' => 'Value'],
            'second' => ['type' => 'integer', 'x-mcp-header' => 'value'],
        ]]];
        foreach (['items', 'additionalProperties', 'not'] as $keyword) {
            yield $keyword => [[$keyword => ['type' => 'string', 'x-mcp-header' => 'Value']]];
        }
        foreach (['allOf', 'anyOf', 'oneOf'] as $keyword) {
            yield $keyword => [[$keyword => [['type' => 'string', 'x-mcp-header' => 'Value']]]];
        }
        yield 'defs' => [['$defs' => ['value' => ['type' => 'string', 'x-mcp-header' => 'Value']]]];
        yield 'nested unreachable' => [['allOf' => [['properties' => ['value' => ['type' => 'string', 'x-mcp-header' => 'Value']]]]]];
    }
}
