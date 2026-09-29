<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Resource;

use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpResourceInfo::class)]
final class McpResourceInfoTest extends UnitTestCase
{
    public function test_that_exact_uris_and_complete_optional_metadata_survive_without_fetching(): void
    {
        $metadata = [
            'uri' => 'context://revision/A%2Fb?x=1#Part', 'name' => '', 'title' => '', 'description' => 'Unicode é',
            'mimeType' => '', 'size' => 0, 'annotations' => ['audience' => ['user', 'assistant'], 'priority' => 0.5, 'lastModified' => ''],
            '_meta' => ['example.org/revision' => ['empty' => StrictJson::fromObject(), 'list' => []]],
            'icons' => [['src' => 'https://icons.test/a.png', 'mimeType' => '', 'sizes' => ['', 'any', '48x48'], 'theme' => 'dark']],
        ];
        $info = McpResourceInfo::fromArray($metadata);
        self::assertSame($metadata['uri'], $info->uri());
        self::assertEquals(StrictJson::fromObject($metadata)->properties(), $info->toArray());
        $metadata['name'] = 'changed';
        $copy = $info->toArray();
        $copy['name'] = 'also changed';
        self::assertSame('', $info->toArray()['name']);
        self::assertSame('urn:example:resource', McpResourceInfo::fromArray(['uri' => 'urn:example:resource', 'name' => 'URN'])->uri());
    }

    #[DataProvider('validOptionalMetadata')]
    public function test_that_protocol_valid_empty_and_optional_values_are_preserved(array $optional): void
    {
        $data = ['uri' => 'test:/a', 'name' => 'a', ...$optional];
        self::assertEquals(StrictJson::fromObject($data)->properties(), McpResourceInfo::fromArray($data)->toArray());
    }

    public static function validOptionalMetadata(): iterable
    {
        yield [[]];
        yield [['annotations' => StrictJson::fromObject(), 'icons' => [], '_meta' => StrictJson::fromObject()]];
        yield [['annotations' => ['audience' => [], 'priority' => 0]]];
        yield [['annotations' => ['priority' => 1]]];
        yield [['size' => 9007199254740991]];
        foreach (['http://example.test/icon', 'data:image/png;base64,YQ==', 'DATA:image/svg+xml;title=%22a%20b%22;base64,YQ%3D%3D',
            'data:image/png;charset=utf-8;base64,YQ==', 'data:image/png;x=a%2Fb;base64,YQ==',
            'data:image/png;x=%22a%5C%22b%22;base64,YQ=='] as $src) {
            yield [['icons' => [['src' => $src]]]];
        }
    }

    #[DataProvider('invalidMetadata')]
    public function test_that_invalid_metadata_cannot_be_published(array $changes): void
    {
        $this->expectException(DomainException::class);
        McpResourceInfo::fromArray([...['uri' => 'test:/a', 'name' => 'a'], ...$changes]);
    }

    public static function invalidMetadata(): iterable
    {
        foreach (['uri' => [null, '', 'relative', 'http://[bad]', 'test:/a b', 'test:/%zz', 'test:/é'],
            'name' => [null, 1, "\xff"], 'title' => [null], 'description' => [false], 'mimeType' => [12],
            'size' => [-1, 1.5, 9007199254740992, '1'], '_meta' => [[], false],
            'annotations' => [[], null, ['audience' => 'user'], ['audience' => ['system']], ['priority' => '1'],
                ['priority' => -0.1], ['priority' => 1.1], ['lastModified' => null]],
            'icons' => [null, ['src' => 'https://example.test/a'], [null], [['src' => 1]], [['src' => 'https://[bad]']],
                [['src' => 'file:/tmp/icon']], [['src' => 'http:/icon']], [['src' => 'relative']],
                [['src' => 'https://example.test', 'mimeType' => false]], [['src' => 'https://example.test', 'theme' => 'auto']],
                [['src' => 'https://example.test', 'sizes' => 'any']], [['src' => 'https://example.test', 'sizes' => [1]]],
                [['src' => 'data:image/png;base64']], [['src' => 'data:image/png,YQ==']],
                [['src' => 'data:text/plain;base64,YQ==']], [['src' => 'data:image/png;base64,']],
                [['src' => 'data:image/png;base64,YR==']], [['src' => 'data:image/png;base64,@@']],
                [['src' => 'data:image/png;bad;base64,YQ==']], [['src' => 'data:image/png;=utf8;base64,YQ==']],
                [['src' => 'data:image/png;x=%00;base64,YQ==']], [['src' => 'data:image/png;x=;base64,YQ==']],
                [['src' => 'data:image/png;x=a/b;base64,YQ==']], [['src' => 'data:image/png;x=%22%00%22;base64,YQ==']]], 'unknown' => ['not silently ignored']] as $field => $values) {
            foreach ($values as $value) { yield [[$field => $value]]; }
        }
    }

    public function test_that_missing_identity_fails(): void
    {
        $this->expectException(DomainException::class);
        McpResourceInfo::fromArray([]);
    }

    public function test_that_exact_encoded_metadata_and_uri_limits_are_enforced(): void
    {
        $metadata = ['uri' => 'test:/'.str_repeat('a', 26), 'name' => str_repeat('é', 20)];
        $bytes = strlen(json_encode($metadata, JSON_THROW_ON_ERROR));
        $limits = new McpResourceLimits(32, $bytes, maxResultBytes: $bytes + 256);
        $info = McpResourceInfo::fromArray($metadata, $limits);
        $info->validateLimits($limits);
        self::assertSame(32, strlen($info->uri()));
        foreach ([new McpResourceLimits(31, $bytes), new McpResourceLimits(32, $bytes - 1)] as $smaller) {
            try { $info->validateLimits($smaller); self::fail('Oversized metadata was accepted.'); }
            catch (DomainException $exception) { self::assertStringContainsString('byte limits', $exception->getMessage()); }
        }
    }

    public function test_that_oversized_metadata_fails_at_construction(): void
    {
        $this->expectException(DomainException::class);
        McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => str_repeat('a', 16384)]);
    }

    public function test_that_nested_extension_metadata_has_a_finite_depth(): void
    {
        $data = 'leaf';
        for ($index = 0; $index < 33; ++$index) { $data = ['nested' => $data]; }
        $this->expectException(DomainException::class);
        McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => 'a', '_meta' => $data]);
    }
}
