<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Prompt;

use Fight\Common\Application\Mcp\Prompt\McpPromptInfo;
use Fight\Common\Application\Mcp\Prompt\McpPromptLimits;
use Fight\Common\Application\Mcp\Prompt\McpPromptOutput;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpPromptInfo::class)]
#[CoversClass(McpPromptOutput::class)]
#[CoversClass(McpPromptLimits::class)]
final class McpPromptValuesTest extends UnitTestCase
{
    public function test_that_metadata_and_arguments_preserve_optional_absence_empty_strings_and_string_types(): void
    {
        $definition = ['name' => 'Review.task-1', 'title' => '', 'description' => '雪', 'arguments' => [
            ['name' => 'id', 'required' => true, 'title' => 'Task', 'description' => 'Exact ID'],
            ['name' => 'optional', 'required' => false],
        ]];
        $info = McpPromptInfo::fromArray($definition);
        self::assertSame('Review.task-1', $info->name());
        self::assertSame($definition, json_decode(json_encode($info->toArray()), true));
        foreach ([['id' => ''], ['id' => '雪', 'optional' => ''], ['id' => str_repeat('x', 16384)]] as $valid) {
            self::assertTrue($info->accepts(StrictJson::fromObject($valid)));
        }
        foreach ([[], ['id' => 1], ['id' => null], ['id' => true], ['id' => []], ['id' => 'a', 'extra' => ''], ['id' => str_repeat('x', 16385)]] as $invalid) {
            self::assertFalse($info->accepts(StrictJson::fromObject($invalid)));
        }
        self::assertFalse($info->accepts(StrictJson::fromData([])));
        self::assertTrue(McpPromptInfo::fromArray(['name' => 'Static'])->accepts(StrictJson::fromObject([])));
        $arguments = array_map(fn($index) => ['name' => 'arg'.$index], range(1, 32));
        $many = McpPromptInfo::fromArray(['name' => 'Many', 'arguments' => $arguments]);
        $values = array_fill_keys(array_column($arguments, 'name'), '');
        self::assertTrue($many->accepts(StrictJson::fromObject($values)));
        self::assertFalse($many->accepts(StrictJson::fromObject([...$values, 'extra' => ''])));
        // The complete encoded object, not raw value bytes, owns the aggregate boundary.
        $values = ['arg1' => str_repeat('x', 16384), 'arg2' => str_repeat('x', 16384), 'arg3' => str_repeat('x', 16384), 'arg4' => ''];
        $overhead = strlen(json_encode($values));
        $values['arg4'] = str_repeat('x', 65536 - $overhead);
        self::assertTrue($many->accepts(StrictJson::fromObject($values)));
        $values['arg4'] .= 'x';
        self::assertFalse($many->accepts(StrictJson::fromObject($values)));
        self::assertFalse($many->accepts(StrictJson::fromObject(['arg1' => str_repeat('雪', 5462)])));
        self::assertFalse($many->accepts(StrictJson::fromObject(array_fill_keys(['arg1', 'arg2', 'arg3'], str_repeat("\n", 16384)))));
    }

    #[DataProvider('invalidDefinitions')]
    public function test_that_malformed_or_oversized_definitions_reject(array $definition): void
    {
        $this->expectException(DomainException::class);
        McpPromptInfo::fromArray($definition);
    }

    public static function invalidDefinitions(): iterable
    {
        foreach ([[], ['name' => ''], ['name' => '1bad'], ['name' => str_repeat('x', 129)], ['name' => 'bad name'], ['name' => '雪'], ['name' => 1], ['name' => 'a', 'extra' => true], ['name' => 'a', 'title' => null], ['name' => 'a', 'description' => 1], ['name' => 'a', 'description' => "\xff"], ['name' => 'a', 'arguments' => null], ['name' => 'a', 'arguments' => ['x' => 'y']], ['name' => 'a', 'arguments' => array_fill(0, 33, ['name' => 'x'])]] as $value) {
            yield [$value];
        }
        foreach ([null, 'text', [], ['name' => ''], ['name' => 'a', 'required' => 'yes'], ['name' => 'a', 'schema' => []], ['name' => 'a', 'title' => false], ['name' => 'a', 'description' => false]] as $argument) {
            yield [['name' => 'a', 'arguments' => [$argument]]];
        }
        yield [['name' => 'a', 'arguments' => [['name' => 'x'], ['name' => 'x']]]];
        yield [['name' => 'a', 'description' => str_repeat('x', 16356)]];
    }

    public function test_that_definition_and_output_encoded_budgets_accept_exact_boundaries(): void
    {
        $definition = ['name' => str_repeat('a', 128), 'description' => ''];
        $definition['description'] = str_repeat('x', 16384 - strlen(json_encode($definition)));
        self::assertSame(16384, strlen(json_encode(McpPromptInfo::fromArray($definition)->toArray())));
        $output = ['description' => '', 'messages' => []];
        $output['description'] = str_repeat('x', 262144 - strlen(json_encode($output)));
        self::assertSame(262144, strlen(json_encode(McpPromptOutput::fromArray($output)->toArray())));
        $output['description'] .= 'x';
        $this->expectException(DomainException::class);
        McpPromptOutput::fromArray($output);
    }

    public function test_that_complete_output_preserves_roles_text_bytes_and_resource_metadata_without_effects(): void
    {
        $link = McpResourceInfo::fromArray(['uri' => 'skill://revision/work/SKILL.md', 'name' => 'Work', 'annotations' => ['audience' => ['user']], '_meta' => ['example.test/version' => 1]]);
        $output = ['description' => '', 'messages' => [
            ['role' => 'user', 'content' => ['type' => 'text', 'text' => "雪\r\n\"\\"]],
            ['role' => 'assistant', 'content' => ['type' => 'resource_link', ...$link->toArray()]],
        ]];
        self::assertSame(json_encode($output), json_encode(McpPromptOutput::fromArray($output)->toArray()));
        self::assertSame(['messages' => []], McpPromptOutput::fromArray(['messages' => []])->toArray());
        $messages = array_fill(0, 128, ['role' => 'assistant', 'content' => ['type' => 'text', 'text' => '']]);
        self::assertCount(128, McpPromptOutput::fromArray(['messages' => $messages])->toArray()['messages']);
        $text = str_repeat('x', 65536);
        self::assertSame($text, McpPromptOutput::fromArray(['messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]]]])->toArray()['messages'][0]->get('content')->get('text'));
    }

    #[DataProvider('invalidOutputs')]
    public function test_that_unsupported_or_oversized_output_never_becomes_partial_success(array $output): void
    {
        $this->expectException(DomainException::class);
        McpPromptOutput::fromArray($output);
    }

    public static function invalidOutputs(): iterable
    {
        foreach ([[], ['messages' => null], ['messages' => ['x' => []]], ['messages' => [], 'description' => null], ['messages' => [], 'extra' => true], ['messages' => array_fill(0, 129, [])]] as $output) {
            yield [$output];
        }
        foreach ([null, [], 'text', ['role' => 'system', 'content' => ['type' => 'text', 'text' => 'x']], ['role' => 'developer', 'content' => ['type' => 'text', 'text' => 'x']], ['role' => 'user', 'content' => [], 'extra' => 1], ['role' => 'user', 'content' => null]] as $message) {
            yield [['messages' => [$message]]];
        }
        foreach ([['type' => 'image', 'data' => 'x'], ['type' => 'resource', 'resource' => []], ['type' => 'text'], ['type' => 'text', 'text' => null], ['type' => 'text', 'text' => 'x', 'extra' => true], ['type' => 'text', 'text' => "\xff"], ['type' => 'text', 'text' => str_repeat('x', 65537)], ['type' => 'text', 'text' => str_repeat('雪', 21846)], ['type' => 'resource_link', 'name' => 'x', 'uri' => '/private'], ['type' => 'resource_link', 'name' => 'x', 'uri' => 'doc://x', 'size' => -1]] as $content) {
            yield [['messages' => [['role' => 'user', 'content' => $content]]]];
        }
        yield [['messages' => array_fill(0, 3, ['role' => 'user', 'content' => ['type' => 'text', 'text' => str_repeat("\n", 65536)]])]];
    }

    #[DataProvider('invalidLimits')]
    public function test_that_limits_reject_nonpositive_and_unbounded_values(array $arguments): void
    {
        $this->expectException(DomainException::class);
        new McpPromptLimits(...$arguments);
    }

    public static function invalidLimits(): iterable
    {
        foreach (['maxPrompts' => 10000, 'maxCatalogBytes' => 16777216, 'pageSize' => 1000, 'maxResultBytes' => 16777216, 'maxResponseBytes' => 16777216] as $name => $max) {
            yield [[$name => 0]];
            yield [[$name => $max + 1]];
        }
    }
}
