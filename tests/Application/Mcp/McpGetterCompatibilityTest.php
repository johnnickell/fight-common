<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\Protocol\McpRequestLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscoveryLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillFile;
use Fight\Common\Application\Mcp\Skill\McpSkillLimits;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpRequestLimits::class)]
#[CoversClass(McpResourceLimits::class)]
#[CoversClass(McpResourceReadLimits::class)]
#[CoversClass(McpSkillDiscoveryLimits::class)]
#[CoversClass(McpSkillFile::class)]
#[CoversClass(McpSkillLimits::class)]
final class McpGetterCompatibilityTest extends UnitTestCase
{
    #[DataProvider('values')]
    public function test_that_getters_preserve_configured_state_after_round_trip(object $value, array $expected): void
    {
        $restored = unserialize(serialize($value));

        foreach ($expected as $property => $state) {
            self::assertSame($state, $value->$property());
            self::assertEquals($state, $restored->$property());
        }
    }

    public static function values(): iterable
    {
        yield 'request defaults' => [new McpRequestLimits(), ['maxBytes' => 1048576, 'maxDepth' => 512]];
        yield 'request named overrides' => [new McpRequestLimits(maxDepth: 2, maxBytes: 37), ['maxBytes' => 37, 'maxDepth' => 2]];
        yield 'resource defaults' => [new McpResourceLimits(), ['maxUriBytes' => 4096, 'maxDescriptorBytes' => 16384, 'pageSize' => 100, 'maxDescriptors' => 10000, 'maxProviders' => 32, 'maxResultBytes' => 1048576]];
        yield 'resource overrides' => [new McpResourceLimits(maxResultBytes: 289, maxProviders: 1, maxDescriptors: 1, pageSize: 1, maxDescriptorBytes: 33, maxUriBytes: 1), ['maxUriBytes' => 1, 'maxDescriptorBytes' => 33, 'pageSize' => 1, 'maxDescriptors' => 1, 'maxProviders' => 1, 'maxResultBytes' => 289]];
        yield 'read defaults' => [new McpResourceReadLimits(), ['maxContentBytes' => 1048576, 'maxResultBytes' => 8388608]];
        yield 'read overrides' => [new McpResourceReadLimits(maxResultBytes: 262, maxContentBytes: 1), ['maxContentBytes' => 1, 'maxResultBytes' => 262]];
        yield 'discovery defaults' => [new McpSkillDiscoveryLimits(), ['pageSize' => 100, 'maxEntries' => 10000, 'maxEntryBytes' => 4194304, 'maxResultBytes' => 8388608, 'maxUriBytes' => 8192]];
        yield 'discovery overrides' => [new McpSkillDiscoveryLimits(maxUriBytes: 7, maxResultBytes: 259, maxEntryBytes: 3, maxEntries: 2, pageSize: 1), ['pageSize' => 1, 'maxEntries' => 2, 'maxEntryBytes' => 3, 'maxResultBytes' => 259, 'maxUriBytes' => 7]];
        yield 'skill defaults' => [new McpSkillLimits(), ['maxFiles' => 512, 'maxTotalBytes' => 16777216, 'maxFrontmatterBytes' => 65536, 'maxDepth' => 32, 'maxEntryBytes' => 4194304]];
        yield 'skill overrides' => [new McpSkillLimits(maxEntryBytes: 9, maxDepth: 3, maxFrontmatterBytes: 5, maxTotalBytes: 7, maxFiles: 2), ['maxFiles' => 2, 'maxTotalBytes' => 7, 'maxFrontmatterBytes' => 5, 'maxDepth' => 3, 'maxEntryBytes' => 9]];
        yield 'text bytes' => [McpSkillFile::fromBytes(bytes: "雪\r\n", path: 'notes/雪.txt'), ['path' => 'notes/雪.txt', 'bytes' => "雪\r\n", 'mimeType' => 'text/plain', 'text' => true, 'digest' => 'sha256:'.hash('sha256', "雪\r\n")]];
        yield 'binary bytes' => [McpSkillFile::fromBytes(text: false, mimeType: 'application/octet-stream', bytes: "\0\1", path: 'binary'), ['path' => 'binary', 'bytes' => "\0\1", 'mimeType' => 'application/octet-stream', 'text' => false, 'digest' => 'sha256:'.hash('sha256', "\0\1")]];
        yield 'empty bytes' => [McpSkillFile::fromBytes('empty', ''), ['path' => 'empty', 'bytes' => '', 'mimeType' => 'text/plain', 'text' => true, 'digest' => 'sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855']];
    }
}
