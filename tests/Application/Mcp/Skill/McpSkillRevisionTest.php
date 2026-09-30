<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Skill;

use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillFile;
use Fight\Common\Application\Mcp\Skill\McpSkillFrontmatterParser;
use Fight\Common\Application\Mcp\Skill\McpSkillLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillRevision;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpSkillRevision::class)]
final class McpSkillRevisionTest extends UnitTestCase
{
    private const URI = 'skill://catalog/r1/work/SKILL.md';
    private const ROOT = "---\r\nname: work\r\ndescription: Test\r\n---\r\nRead [guide](references/雪.md).\r\n";

    public function test_that_revision_derives_one_complete_manifest_from_owned_exact_bytes(): void
    {
        $parser = $this->parser(['name' => 'work', 'description' => 'Test', 'unknown' => ['flags' => [true, null, 1]], 'license' => 'MIT', 'allowed-tools' => 'Read', 'metadata' => StrictJson::fromObject([])]);
        $members = [
            McpSkillFile::fromBytes('templates/a.twig', '{{ name }}'),
            McpSkillFile::fromBytes('references/雪.md', "雪\r\n"),
            McpSkillFile::fromBytes('nested/SKILL.md', 'not activated'),
            McpSkillFile::fromBytes('scripts/a.php', '<?php throw new Exception();'),
            McpSkillFile::fromBytes('assets/image', "\0\xff", 'image/png', false),
            McpSkillFile::fromBytes('empty', ''),
            McpSkillFile::fromBytes('SKILL.md', self::ROOT),
        ];
        $revision = McpSkillRevision::fromFiles(self::URI, $members, $parser);
        self::assertSame(["name: work\r\ndescription: Test\r\n"], $parser->calls);
        $entry = json_decode($revision->entry()->toString(), true);
        self::assertSame(self::URI, $entry['uri']);
        self::assertSame([true, null, 1], $entry['frontmatter']['unknown']['flags']);
        self::assertCount(7, $entry['resources']);
        self::assertCount(7, $revision->resources());
        $addresses = array_keys($revision->resources());
        $sorted = $addresses; sort($sorted, SORT_STRING);
        self::assertSame($sorted, $addresses);
        foreach ($members as $member) {
            $uri = 'skill://catalog/r1/work/'.$member->uriPath();
            self::assertSame($member, $revision->file($uri));
            $manifest = array_column($entry['resources'], null, 'uri')[$uri];
            self::assertSame(['uri' => $uri, 'digest' => 'sha256:'.hash('sha256', $member->bytes), 'size' => strlen($member->bytes)], $manifest);
            self::assertSame(strlen($member->bytes), $revision->resources()[$uri]->toArray()['size']);
        }
        $members = [];
        $descriptors = $revision->resources(); unset($descriptors[self::URI]);
        self::assertSame(self::ROOT, $revision->file(self::URI)->bytes);
        self::assertCount(7, $revision->resources());
        self::assertNull($revision->file('skill://catalog/r1/work/%53KILL.md'));
        self::assertNull($revision->file('skill://catalog/r1/work/references/../SKILL.md'));
        self::assertCount(1, $parser->calls);
    }

    public function test_that_512_members_and_16_mib_aggregate_fit_without_reading_during_discovery(): void
    {
        $root = McpSkillFile::fromBytes('SKILL.md', self::ROOT);
        $members = [$root];
        $remaining = 16777216 - strlen(self::ROOT);
        for ($i = 0; $i < 511; ++$i) {
            $size = min(65536, $remaining); $remaining -= $size;
            $members[] = McpSkillFile::fromBytes('assets/'.$i, str_repeat('x', $size));
        }
        $parser = $this->parser();
        $revision = McpSkillRevision::fromFiles(self::URI, $members, $parser);
        self::assertCount(512, $revision->resources());
        self::assertSame(16777216, array_sum(array_column(json_decode($revision->entry()->toString(), true)['resources'], 'size')));
        $revision->resources(); $revision->entry(); $revision->file(self::URI);
        self::assertCount(1, $parser->calls);
    }

    public function test_that_new_addresses_keep_old_revision_bytes_unchanged(): void
    {
        $old = McpSkillRevision::fromFiles(self::URI, [McpSkillFile::fromBytes('SKILL.md', self::ROOT)], $this->parser());
        $newUri = str_replace('/r1/', '/r2/', self::URI);
        $new = McpSkillRevision::fromFiles($newUri, [McpSkillFile::fromBytes('SKILL.md', self::ROOT.'New')], $this->parser());
        self::assertSame(self::ROOT, $old->file(self::URI)->bytes);
        self::assertSame(self::ROOT.'New', $new->file($newUri)->bytes);
        self::assertNull($old->file($newUri));
        self::assertNull($new->file(self::URI));
        self::assertNotSame($old->entry()->get('resources'), $new->entry()->get('resources'));
    }

    public function test_that_exact_publication_limits_accept_complete_metadata_and_raw_bytes(): void
    {
        $parser = $this->parser();
        $revision = McpSkillRevision::fromFiles(self::URI, [McpSkillFile::fromBytes('SKILL.md', self::ROOT)], $parser);
        $limit = strlen($revision->entry()->toString());
        $bounded = McpSkillRevision::fromFiles(self::URI, [McpSkillFile::fromBytes('SKILL.md', self::ROOT)], $parser,
            new McpSkillLimits(1, strlen(self::ROOT), strlen($parser->result->toString()), 32, $limit),
            readLimits: new McpResourceReadLimits(strlen(self::ROOT), 1024));
        self::assertSame($revision->entry()->toString(), $bounded->entry()->toString());
    }

    #[DataProvider('invalidRevisions')]
    public function test_that_revision_rejects_incomplete_ambiguous_or_oversized_publication(array $arguments): void
    {
        $this->expectException(DomainException::class);
        McpSkillRevision::fromFiles(...array_replace([
            'uri' => self::URI,
            'members' => [McpSkillFile::fromBytes('SKILL.md', self::ROOT)],
            'parser' => $this->parser(),
        ], $arguments));
    }

    public static function invalidRevisions(): iterable
    {
        foreach (['work/SKILL.md', 'skill://host/work/skill.md', 'skill://host/work/SKILL.md?x', 'skill://host/work/SKILL.md#x', 'skill://user@host/work/SKILL.md', 'skill://host/../work/SKILL.md', 'skill://host/%2e%2e/work/SKILL.md', 'skill://host/%252e/work/SKILL.md', 'skill://host/r%2Fwork/SKILL.md', 'skill://host/%77ork/SKILL.md', 'skill://host/other/SKILL.md', 'skill://host/SKILL.md', 'skill://host//work/SKILL.md'] as $uri) { yield [['uri' => $uri]]; }
        yield 'URI limit' => [['resourceLimits' => new McpResourceLimits(maxUriBytes: 1)]];
        yield 'empty set' => [['members' => []]];
        yield 'wrong member type' => [['members' => ['not a file']]];
        yield 'missing root' => [['members' => [McpSkillFile::fromBytes('nested/SKILL.md', self::ROOT)]]];
        yield 'binary root' => [['members' => [McpSkillFile::fromBytes('SKILL.md', self::ROOT, text: false)]]];
        yield 'duplicate root' => [['members' => [McpSkillFile::fromBytes('SKILL.md', self::ROOT), McpSkillFile::fromBytes('SKILL.md', self::ROOT)]]];
        yield 'file directory collision' => [['members' => [McpSkillFile::fromBytes('SKILL.md', self::ROOT), McpSkillFile::fromBytes('a', ''), McpSkillFile::fromBytes('a/b', '')]]];
        yield 'member count' => [['members' => [McpSkillFile::fromBytes('SKILL.md', self::ROOT), McpSkillFile::fromBytes('a', '')], 'limits' => new McpSkillLimits(maxFiles: 1)]];
        yield 'aggregate bytes' => [['limits' => new McpSkillLimits(maxTotalBytes: strlen(self::ROOT) - 1)]];
        yield 'raw read bytes' => [['readLimits' => new McpResourceReadLimits(1, 1024)]];
        yield 'encoded read metadata' => [['readLimits' => new McpResourceReadLimits(128, 1024)]];
        yield 'entry bytes' => [['limits' => new McpSkillLimits(maxFrontmatterBytes: 64, maxEntryBytes: 64)]];
        foreach (["no frontmatter", "---\nname: work\n", "---\rname: work\r---\r", "\xef\xbb\xbf---\nname: work\n---\n"] as $bytes) { yield [['members' => [McpSkillFile::fromBytes('SKILL.md', $bytes)]]]; }
        yield 'bounded delimiter' => [['members' => [McpSkillFile::fromBytes('SKILL.md', "---\n".str_repeat('a', 100)."\n---\n")], 'limits' => new McpSkillLimits(maxFrontmatterBytes: 20)]];
        yield 'just over raw frontmatter' => [['members' => [McpSkillFile::fromBytes('SKILL.md', "---\n".str_repeat('a', 20)."\n---\n")], 'limits' => new McpSkillLimits(maxFrontmatterBytes: 20)]];
    }

    #[DataProvider('invalidFrontmatter')]
    public function test_that_required_and_optional_defined_fields_are_validated_without_dropping_unknowns(mixed $data): void
    {
        $this->expectException(DomainException::class);
        McpSkillRevision::fromFiles(self::URI, [McpSkillFile::fromBytes('SKILL.md', self::ROOT)], $this->parser($data));
    }

    public static function invalidFrontmatter(): iterable
    {
        foreach ([[], null, 'text', ['name' => 'work'], ['description' => 'd']] as $data) { yield [$data]; }
        foreach (['', '-a', 'a-', 'a--b', 'UPPER', 'a.b', 'a/b', str_repeat('a', 65), 1] as $name) { yield [['name' => $name, 'description' => 'd']]; }
        foreach (['', '  ', str_repeat('雪', 1025), 1] as $description) { yield [['name' => 'work', 'description' => $description]]; }
        foreach (['license', 'allowed-tools', 'compatibility'] as $field) { yield [['name' => 'work', 'description' => 'd', $field => []]]; }
        foreach (['', str_repeat('雪', 501)] as $compatibility) { yield [['name' => 'work', 'description' => 'd', 'compatibility' => $compatibility]]; }
        foreach ([[], 'text', ['version' => 1], null] as $metadata) { yield [['name' => 'work', 'description' => 'd', 'metadata' => $metadata]]; }
        yield 'parser encoded overflow' => [['name' => 'work', 'description' => 'd', 'unknown' => str_repeat('x', 65536)]];
    }

    public function test_that_unicode_names_and_valid_compatibility_remain_unchanged(): void
    {
        $revision = McpSkillRevision::fromFiles('skill://host/r1/%E9%9B%AA/SKILL.md', [McpSkillFile::fromBytes('SKILL.md', self::ROOT)], $this->parser(['name' => '雪', 'description' => 'd', 'compatibility' => 'PHP']));
        self::assertSame('雪', $revision->entry()->get('frontmatter')->get('name'));
    }

    private function parser(mixed $data = ['name' => 'work', 'description' => 'Test']): McpSkillFrontmatterParser
    {
        return new class(StrictJson::fromData($data)) implements McpSkillFrontmatterParser {
            public array $calls = [];
            public function __construct(public StrictJson $result) {}
            public function parse(string $yaml, McpSkillLimits $limits): StrictJson { $this->calls[] = $yaml; return $this->result; }
        };
    }
}
