<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Skill;

use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpSkillRevision
 */
final readonly class McpSkillRevision
{
    /**
     * Constructs McpSkillRevision
     *
     * @phpstan-param array<string, McpSkillFile> $files
     * @phpstan-param array<string, McpResourceInfo> $resources
     */
    private function __construct(private StrictJson $entry, private array $files, private array $resources)
    {
    }

    /**
     * Creates a complete bounded snapshot from the consumer's explicit immutable member set
     *
     * The manifest is derived from these exact owned bytes, never an independently mutable callback.
     * Consumers own completeness of their source export, bounded acquisition, unique revision addresses,
     * atomic publication and retention. Construction validates once; discovery does not rehash content.
     *
     * @phpstan-param iterable<mixed> $members
     */
    public static function fromFiles(
        string $uri,
        iterable $members,
        McpSkillFrontmatterParser $parser,
        McpSkillLimits $limits = new McpSkillLimits(),
        McpResourceLimits $resourceLimits = new McpResourceLimits(),
        McpResourceReadLimits $readLimits = new McpResourceReadLimits()
    ): self {
        $root = self::root($uri, $resourceLimits);
        $files = [];
        $resources = [];
        $paths = [];
        $total = 0;
        foreach ($members as $member) {
            if (!$member instanceof McpSkillFile || count($files) >= $limits->maxFiles) {
                throw new DomainException('Skill revisions require a bounded complete set of immutable files.');
            }

            $address = $root.$member->uriPath();
            $total += strlen($member->bytes);
            if (isset($files[$address]) || $total > $limits->maxTotalBytes) {
                throw new DomainException('Skill revision members are duplicated or exceed the aggregate byte budget.');
            }

            $info = McpResourceInfo::fromArray([
                'uri'      => $address,
                'name'     => $member->path,
                'mimeType' => $member->mimeType,
                'size'     => strlen($member->bytes)
            ], $resourceLimits);
            $readLimits->validate($info);
            $files[$address] = $member;
            $resources[$address] = $info;
            $paths[$member->path] = true;
        }

        if (!isset($files[$uri]) || !$files[$uri]->text) {
            throw new DomainException('Skill revisions require one UTF-8 root SKILL.md.');
        }

        foreach (array_keys($paths) as $path) {
            $segments = explode('/', (string) $path);
            array_pop($segments);
            while ($segments !== []) {
                if (isset($paths[implode('/', $segments)])) {
                    throw new DomainException('Skill files cannot also be parent directories.');
                }

                array_pop($segments);
            }
        }

        $frontmatter = self::frontmatter($files[$uri]->bytes, $parser, $limits);
        if (!str_ends_with($root, '/'.rawurlencode($frontmatter->get('name')).'/')) {
            throw new DomainException('Skill URI parent directory must match its frontmatter name.');
        }

        ksort($files, SORT_STRING);
        ksort($resources, SORT_STRING);
        $manifest = [];
        foreach ($files as $address => $file) {
            $manifest[] = ['uri' => $address, 'digest' => $file->digest, 'size' => strlen($file->bytes)];
        }

        $entry = StrictJson::fromObject(
            ['uri' => $uri, 'frontmatter' => $frontmatter, 'resources' => $manifest],
            $limits->maxDepth + 2
        );
        if (strlen($entry->toString()) > $limits->maxEntryBytes) {
            throw new DomainException('Skill complete entry exceeds its encoded metadata budget.');
        }

        return new self($entry, $files, $resources);
    }

    /**
     * Returns complete immutable neutral metadata without exposing source bytes
     */
    public function entry(): StrictJson
    {
        return $this->entry;
    }

    /**
     * Returns descriptors in exact bytewise URI order without reading or hashing content
     *
     * @return array<string, McpResourceInfo>
     */
    public function resources(): array
    {
        return $this->resources;
    }

    /**
     * Returns only an exact registered member without URI normalization or fallback
     */
    public function file(string $uri): ?McpSkillFile
    {
        return $this->files[$uri] ?? null;
    }

    /**
     * Returns the exact canonical hierarchical root without resolving a host or storage path
     */
    private static function root(string $uri, McpResourceLimits $limits): string
    {
        if (
            strlen($uri) > $limits->maxUriBytes
            || preg_match('~\A[a-z][a-z0-9+.-]*:(?://[A-Za-z0-9.-]+(?::[0-9]+)?)?(/[^?#]+)\z~D', $uri, $match) !== 1
            || !str_ends_with($uri, '/SKILL.md')
        ) {
            throw new DomainException('Skill revisions require a canonical absolute hierarchical SKILL.md URI.');
        }

        $path = substr($match[1], 1);
        $decoded = rawurldecode($path);
        McpSkillFile::validatePath($decoded);
        if (implode('/', array_map(rawurlencode(...), explode('/', $decoded))) !== $path) {
            throw new DomainException('Skill URI path must use canonical segment encoding without escaped separators.');
        }

        return substr($uri, 0, -strlen('SKILL.md'));
    }

    /**
     * Returns validated complete frontmatter while preserving the original root file bytes
     */
    private static function frontmatter(
        string $bytes,
        McpSkillFrontmatterParser $parser,
        McpSkillLimits $limits
    ): StrictJson {
        // Search only a bounded prefix; never parse a file body as YAML or normalize CRLF in served bytes.
        $prefix = substr($bytes, 0, $limits->maxFrontmatterBytes + 16);
        if (preg_match('/\A---\r?\n(.*?)^---(?:\r?\n|\z)/ms', $prefix, $match) !== 1) {
            throw new DomainException('Skill root requires bounded delimited YAML frontmatter.');
        }

        if (strlen($match[1]) > $limits->maxFrontmatterBytes) {
            throw new DomainException('Skill frontmatter exceeds its raw byte budget.');
        }

        $data = $parser->parse($match[1], $limits);
        $data = StrictJson::fromData($data, $limits->maxDepth);
        if (!$data->isObject() || strlen($data->toString()) > $limits->maxFrontmatterBytes) {
            throw new DomainException('Skill frontmatter must retain a bounded complete JSON object.');
        }

        $name = $data->get('name');
        $description = $data->get('description');
        if (
            !is_string($name) || mb_strlen($name) > 64
            || preg_match('/\A[\p{Ll}\p{Lo}\p{Nd}]+(?:-[\p{Ll}\p{Lo}\p{Nd}]+)*\z/uD', $name) !== 1
            || !is_string($description) || trim($description) === '' || mb_strlen($description) > 1024
        ) {
            throw new DomainException('Skill frontmatter requires a valid name and non-empty bounded description.');
        }

        foreach (['license', 'compatibility', 'allowed-tools'] as $field) {
            if ($data->has($field) && !is_string($data->get($field))) {
                throw new DomainException('Defined Skill frontmatter text fields must retain string values.');
            }
        }

        if (
            $data->has('compatibility')
            && (trim($data->get('compatibility')) === '' || mb_strlen($data->get('compatibility')) > 500)
        ) {
            throw new DomainException('Skill compatibility must be non-empty and at most 500 characters.');
        }

        $metadata = $data->get('metadata');
        if (
            $data->has('metadata')
            && (!$metadata instanceof StrictJson || !$metadata->isObject()
                || !array_all($metadata->properties(), fn($value): bool => is_string($value)))
        ) {
            throw new DomainException('Skill metadata must be a string-valued mapping.');
        }

        return $data;
    }
}
