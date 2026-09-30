<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Symfony;

use Fight\Common\Adapter\Mcp\Symfony\SymfonyMcpSkillFrontmatterParser;
use Fight\Common\Application\Mcp\Skill\McpSkillFile;
use Fight\Common\Application\Mcp\Skill\McpSkillLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillRevision;
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

    public function test_that_lexical_budget_exhaustion_rejects_without_looping_or_dropping_fields(): void
    {
        $previous = ini_set('pcre.backtrack_limit', '0');
        try {
            $this->expectException(DomainException::class);
            (new SymfonyMcpSkillFrontmatterParser())->parse('x: y', new McpSkillLimits());
        } finally {
            ini_set('pcre.backtrack_limit', $previous);
        }
    }

    public function test_that_large_plain_and_quoted_scalars_fit_a_validated_budget_override(): void
    {
        $parser = new SymfonyMcpSkillFrontmatterParser();
        $value = str_repeat('x', 900000);
        foreach (['unknown: '.$value, 'unknown: "'.$value.'"', '{unknown: '.$value.'}'] as $yaml) {
            self::assertSame($value, $parser->parse($yaml, new McpSkillLimits(maxFrontmatterBytes: 1048576))->get('unknown'));
        }
    }

    #[DataProvider('invalidYaml')]
    public function test_that_parser_rejects_ambiguous_executable_non_json_or_excessive_input(string $yaml, ?McpSkillLimits $limits = null): void
    {
        $this->expectException(DomainException::class);
        (new SymfonyMcpSkillFrontmatterParser())->parse($yaml, $limits ?? new McpSkillLimits());
    }

    #[DataProvider('lossyMappings')]
    public function test_that_mapping_keys_never_overwrite_or_relocate_authored_fields(string $yaml): void
    {
        $this->expectException(DomainException::class);
        (new SymfonyMcpSkillFrontmatterParser())->parse($yaml, new McpSkillLimits());
    }

    #[DataProvider('lossyMappings')]
    public function test_that_revision_publication_rejects_ambiguous_mapping_input(string $yaml): void
    {
        $this->expectException(DomainException::class);
        McpSkillRevision::fromFiles('skill://catalog/r1/work/SKILL.md', [
            McpSkillFile::fromBytes('SKILL.md', "---\nname: work\ndescription: test\n".$yaml."\n---\nBody"),
        ], new SymfonyMcpSkillFrontmatterParser());
    }

    #[DataProvider('literalFields')]
    public function test_that_quoted_keys_and_merge_looking_scalar_content_survive_publication(string $yaml, array $expected): void
    {
        $parser = new SymfonyMcpSkillFrontmatterParser();
        self::assertSame($expected, json_decode($parser->parse($yaml, new McpSkillLimits())->toString(), true));
        $bytes = "---\r\nname: work\r\ndescription: test\r\n".str_replace("\n", "\r\n", $yaml)."\r\n---\r\nBody\r\n";
        $uri = 'skill://catalog/r1/work/SKILL.md';
        $revision = McpSkillRevision::fromFiles($uri, [McpSkillFile::fromBytes('SKILL.md', $bytes)], $parser);
        self::assertSame(['name' => 'work', 'description' => 'test'] + $expected, json_decode($revision->entry()->get('frontmatter')->toString(), true));
        self::assertSame($bytes, $revision->file($uri)->bytes);
        self::assertSame('sha256:'.hash('sha256', $bytes), $revision->entry()->get('resources')[0]->get('digest'));
    }

    public static function literalFields(): iterable
    {
        yield 'quoted and escaped nested keys' => ['"unknown": {"quoted:key": {"\\u96ea": value}, "<not-merge>": true}', ['unknown' => ['quoted:key' => ['雪' => 'value'], '<not-merge>' => true]]];
        yield 'merge symbol values' => ['unknown: ["<<", \'<<\', <<, "\\x3c\\u003c"]', ['unknown' => ['<<', '<<', '<<', '<<']]];
        yield 'quoted syntax is text' => ['unknown: "<<: {}"', ['unknown' => '<<: {}']];
        yield 'multiline quoted syntax is text' => ["unknown: 'first\n  <<: {}'", ['unknown' => 'first <<: {}']];
        yield 'literal block with empty lines' => ["unknown: |-\n  <<: {}\n\n  \"<<\": {}", ['unknown' => "<<: {}\n\n\"<<\": {}"]];
        yield 'folded sequence scalar' => ["unknown:\n  - >-\n    <<: {}\n    after\n  - end", ['unknown' => ['<<: {} after', 'end']]];
        yield 'anchored sequence scalar' => ["unknown:\n  - &literal |-\n    <<: {}\n  - end", ['unknown' => ['<<: {}', 'end']]];
        yield 'nested sequence scalar' => ["unknown:\n  - - |-\n      <<: {}", ['unknown' => [['<<: {}']]]];
        yield 'compact sequence mapping scalar' => ["unknown:\n  - text: |-\n      <<: {}\n    other: intact", ['unknown' => [['text' => '<<: {}', 'other' => 'intact']]]];
        yield 'explicit indentation' => ["unknown: |2-\n    indented\n  <<: {}", ['unknown' => "  indented\n<<: {}"]];
        yield 'comment with digit' => ["unknown: |- # 9 <<: {}\n  <<: {}\n# <<: {}\nother: intact", ['unknown' => '<<: {}', 'other' => 'intact']];
        yield 'embedded quotes in plain values' => ["unknown: hello \" there\nother: world \" end", ['unknown' => 'hello " there', 'other' => 'world " end']];
        yield 'tagged scalar' => ['unknown: !!str 42', ['unknown' => '42']];
        yield 'tagged mapping keys' => ["!!str unknown:\n  !!binary eA==: preserved", ['unknown' => ['x' => 'preserved']]];
        yield 'binary decoded value' => ['unknown: !!binary PDw=', ['unknown' => '<<']];
        yield 'tagged quoted value' => ['unknown: !!binary "SGVsbG8="', ['unknown' => 'Hello']];
        yield 'independent flow mappings' => ['unknown: [{x: null}, {x: 2}, {x: {x: 3}}]', ['unknown' => [['x' => null], ['x' => 2], ['x' => ['x' => 3]]]]];
        yield 'large plain value within budget' => ['unknown: '.str_repeat('x', 60000), ['unknown' => str_repeat('x', 60000)]];
        yield 'large flow plain value within budget' => ['unknown: {value: '.str_repeat('x', 60000).'}', ['unknown' => ['value' => str_repeat('x', 60000)]]];
        yield 'large quoted value within budget' => ['unknown: "'.str_repeat('x', 60000).'"', ['unknown' => str_repeat('x', 60000)]];
        yield 'large escaped value within budget' => ['unknown: "'.str_repeat('\\"', 20000).'"', ['unknown' => str_repeat('"', 20000)]];
        yield 'empty literal scalar' => ['unknown: |', ['unknown' => '']];
        yield 'empty folded scalar' => ["unknown: >-\n\n", ['unknown' => '']];
        yield 'double quoted escapes' => ['unknown: "quote \\" slash \\\\ tab \\t"', ['unknown' => "quote \" slash \\ tab \t"]];
        yield 'single quoted escaping' => ["unknown: 'it''s <<: {}'", ['unknown' => "it's <<: {}"]];
    }

    public static function lossyMappings(): iterable
    {
        yield 'block duplicate after merge' => ["<<: {}\ndescription: first\ndescription: second"];
        yield 'nested duplicate after merge' => ["unknown:\n  <<: {}\n  x: first\n  x: second"];
        yield 'flow duplicate after merge' => ['unknown: {<<: {}, x: first, x: second}'];
        yield 'quoted block key' => ["'<<': {a: preserved}"];
        yield 'explicit string key' => ["!!str '<<': {a: preserved}"];
        yield 'binary decoded key' => ['!!binary PDw=: {a: preserved}'];
        yield 'quoted binary decoded key' => ["!!binary 'PDw=': {a: preserved}"];
        yield 'quoted flow key' => ['unknown: {"<<": {a: preserved}}'];
        yield 'escaped key' => ['"\\x3c\\u003c": {a: preserved}'];
        yield 'nested sequence key' => ["unknown:\n  - <<: {a: preserved}"];
        yield 'merge sequence' => ['unknown: {<<: [{a: first}, {a: second}]}'];
        yield 'null duplicate scalar' => ['unknown: {x: null, x: second}'];
        yield 'null duplicate sequence' => ['unknown: {x: null, x: [second]}'];
        yield 'null duplicate mapping' => ['unknown: {x: null, x: {a: second}}'];
        yield 'escaped duplicate null key' => ['unknown: {x: null, "\\x78": second}'];
        yield 'anchored merge' => ["<<: &anchor\n  a: preserved"];
        yield 'wide escaped key' => ['"\\U0000003C<": {a: preserved}'];
        yield 'escaped line continuation key' => ["\"<\\\n<\": {a: preserved}"];
        yield 'flow without key spacing' => ['unknown: {"<<":{a: preserved}}'];
        yield 'merge after scalar' => ["unknown: |-\n  literal\n<<: {a: preserved}"];
        yield 'merge after compact mapping scalar' => ["unknown:\n  - text: |-\n      literal\n    <<: {a: preserved}"];
        yield 'merge after anchored scalar' => ["unknown:\n  - &literal |-\n    text\n  - <<: {a: preserved}"];
        yield 'merge after compact sequence scalar' => ["unknown:\n  - |-\n    literal\n  - <<: {a: preserved}"];
        yield 'quotes in plain strings cannot hide keys' => ["unknown: hello \"\n<<: {a: preserved}\nother: end\""];
        yield 'empty scalar cannot hide key' => ["unknown: |\n<<: {a: preserved}"];
        yield 'nested flow sequence' => ['unknown: [{"<<": {a: preserved}}]'];
    }

    public static function invalidYaml(): iterable
    {
        foreach ([
            '', '[]', 'null', '42', "a: 1\na: 2", '{a: 1, a: 2}', "x: {a: 1, a: 2}",
            'a: "unterminated', "a: 'unterminated", '--- {<<: {a: relocated}}',
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
