<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Symfony;

use Fight\Common\Adapter\Mcp\Symfony\SymfonyMcpSkillFrontmatterParser;
use Fight\Common\Application\Mcp\Skill\McpSkillLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SymfonyMcpSkillFrontmatterParser::class)]
final class SymfonyMcpSkillFrontmatterParserTest extends UnitTestCase
{
    public function test_that_parser_preserves_all_json_fields_and_object_list_distinctions(): void
    {
        $yaml = "name: work\r\ndescription: >-\r\n  Read this\r\n  carefully\r\nlicense: MIT\r\nallowed-tools: Read\r\nmetadata: {version: '1.0'}\r\nunknown: {empty: {}, list: [], values: [true, false, null, 42, 1.5, 雪], '0': value}\r\n";
        $data = (new SymfonyMcpSkillFrontmatterParser())->parse($yaml, new McpSkillLimits());
        self::assertSame(['name' => 'work', 'description' => 'Read this carefully', 'license' => 'MIT', 'allowed-tools' => 'Read', 'metadata' => ['version' => '1.0'], 'unknown' => ['empty' => [], 'list' => [], 'values' => [true, false, null, 42, 1.5, '雪'], '0' => 'value']], json_decode($data->toString(), true));
        self::assertTrue($data->get('unknown')->get('empty')->isObject());
        self::assertSame([], $data->get('unknown')->get('list'));
        self::assertSame('2026-01-01', (new SymfonyMcpSkillFrontmatterParser())->parse("date: '2026-01-01'", new McpSkillLimits())->get('date'));
    }

    public function test_that_exact_raw_and_encoded_budgets_are_honored(): void
    {
        $parser = new SymfonyMcpSkillFrontmatterParser();
        self::assertSame('{"a":"b"}', $parser->parse('a: b     ', new McpSkillLimits(maxFrontmatterBytes: 9))->toString());
    }

    #[DataProvider('invalidYaml')]
    public function test_that_parser_rejects_ambiguous_executable_non_json_or_excessive_input(string $yaml, ?McpSkillLimits $limits = null): void
    {
        $this->expectException(DomainException::class);
        (new SymfonyMcpSkillFrontmatterParser())->parse($yaml, $limits ?? new McpSkillLimits());
    }

    public static function invalidYaml(): iterable
    {
        foreach ([
            '', '[]', 'null', '42', "a: 1\na: 2", '{a: 1, a: 2}', "x: {a: 1, a: 2}",
            'object: !php/object O:8:"stdClass":0:{}', 'constant: !php/const PHP_VERSION', 'include: !include secret',
            'custom: !anything foo', 'date: 2026-01-01', 'x: .nan', 'x: .inf', "x: \xff",
            "a: &a [1, 2]\nb: *a", "a: &a 1\nb: *a", "a: &a {x: y}\nb: {<<: *a}",
            "a: &a [*a]", 'a: "\\0key"',
        ] as $yaml) {
            // A NUL in a string value is JSON-representable, so use a forbidden object key instead.
            if ($yaml === 'a: "\\0key"') { $yaml = '"\\0key": value'; }
            yield [$yaml];
        }
        yield 'raw bytes' => ['a: '.str_repeat('x', 20), new McpSkillLimits(maxFrontmatterBytes: 10)];
        yield 'encoded bytes' => ['a: 雪', new McpSkillLimits(maxFrontmatterBytes: 10)];
        yield 'block nesting' => ["a:\n  b:\n    c:\n      d: value", new McpSkillLimits(maxDepth: 1)];
        yield 'inline nesting' => ['a: [[[[[1]]]]]', new McpSkillLimits(maxDepth: 2)];
    }
}
