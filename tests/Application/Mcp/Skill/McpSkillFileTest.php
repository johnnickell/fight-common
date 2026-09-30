<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Skill;

use Fight\Common\Application\Mcp\Skill\McpSkillFile;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpSkillFile::class)]
final class McpSkillFileTest extends UnitTestCase
{
    public function test_that_file_retains_exact_bytes_and_canonical_single_encoding(): void
    {
        $bytes = "雪\r\n{{ template }}";
        $file = McpSkillFile::fromBytes('references/雪 name.md', $bytes, expectedSize: strlen($bytes), expectedDigest: 'sha256:'.hash('sha256', $bytes));
        self::assertSame($bytes, $file->bytes);
        self::assertSame('references/雪 name.md', $file->path);
        self::assertSame('references/%E9%9B%AA%20name.md', $file->uriPath());
        self::assertSame('text/plain', $file->mimeType);
        self::assertTrue($file->text);
        self::assertSame('sha256:'.hash('sha256', $bytes), $file->digest);
        self::assertSame('', McpSkillFile::fromBytes('empty', '')->bytes);
        self::assertSame("\xff\0", McpSkillFile::fromBytes('binary', "\xff\0", text: false)->bytes);
    }

    #[DataProvider('invalidFiles')]
    public function test_that_file_rejects_ambiguous_paths_invalid_bytes_and_integrity_mismatches(array $arguments): void
    {
        $this->expectException(DomainException::class);
        McpSkillFile::fromBytes(...$arguments);
    }

    public static function invalidFiles(): iterable
    {
        foreach (['', '/a', 'a/', 'a//b', '.', '..', 'a/../b', './a', 'a/./b', 'a\\b', 'a%2fb', '%252e%252e', 'http:x', 'a?b', 'a#b', "a\0", "a\x7f", "\xff", str_repeat('a', 2049)] as $path) {
            yield 'path '.bin2hex(substr($path, 0, 30)) => [[$path, '']];
        }
        yield 'invalid text' => [['a', "\xff"]];
        yield 'oversized bytes' => [['a', str_repeat('a', 16777217)]];
        foreach (['', str_repeat('a', 257), "text/plain\n", "\xff"] as $type) { yield [ ['a', '', $type] ]; }
        yield 'size mismatch' => [['a', 'body', 'text/plain', true, 3]];
        yield 'digest mismatch' => [['a', 'body', 'text/plain', true, null, 'sha256:'.str_repeat('0', 64)]];
        yield 'digest casing' => [['a', 'body', 'text/plain', true, null, strtoupper('sha256:'.hash('sha256', 'body'))]];
    }
}
