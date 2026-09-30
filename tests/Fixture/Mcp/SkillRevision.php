<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Adapter\Mcp\Symfony\SymfonyMcpSkillFrontmatterParser;
use Fight\Common\Application\Mcp\Skill\McpSkillFile;
use Fight\Common\Application\Mcp\Skill\McpSkillRevision;

final class SkillRevision
{
    public const ROOT = "---\r\nname: work\r\ndescription: Read explicit revision files\r\nlicense: MIT\r\ncompatibility: PHP\r\nmetadata: {author: Example, version: '1'}\r\nallowed-tools: Read\r\nunknown: {values: [true, null, 1, 雪], empty: {}, list: []}\r\n---\r\nRead [guide](references/guide.md).\r\n";
    public const GUIDE = "# Guide 雪\r\nOriginal café bytes.\r\n";
    public const BINARY = "\0\xff\x80\r\n";

    public static function create(string $version = 'v1'): McpSkillRevision
    {
        return McpSkillRevision::fromFiles('skill://catalog/'.$version.'/work/SKILL.md', [
            McpSkillFile::fromBytes('SKILL.md', self::ROOT.($version === 'v1' ? '' : $version)),
            McpSkillFile::fromBytes('references/guide.md', self::GUIDE),
            McpSkillFile::fromBytes('templates/notice.twig', '{{ consumer_owned }}'),
            McpSkillFile::fromBytes('scripts/test.php', '<?php throw new Exception("Must not execute");'),
            McpSkillFile::fromBytes('nested/SKILL.md', 'Not activated or parsed'),
            McpSkillFile::fromBytes('assets/image', self::BINARY, 'application/octet-stream', false),
            McpSkillFile::fromBytes('empty', ''),
        ], new SymfonyMcpSkillFrontmatterParser());
    }
}
