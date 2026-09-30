<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Skill;

use Fight\Common\Adapter\Mcp\Symfony\SymfonyMcpSkillFrontmatterParser;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Resource\McpReadableResourceProvider;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceContent;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillAvailability;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscovery;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscoveryLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillFile;
use Fight\Common\Application\Mcp\Skill\McpSkillResources;
use Fight\Common\Application\Mcp\Skill\McpSkillRevision;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\ReadableResources;
use Fight\Test\Common\Fixture\Mcp\SkillRevision;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use Throwable;

#[CoversClass(McpSkillDiscovery::class)]
#[CoversClass(McpSkillDiscoveryLimits::class)]
#[CoversClass(McpSkillResources::class)]
#[CoversClass(McpResourceDiscovery::class)]
final class McpSkillDiscoveryTest extends UnitTestCase
{
    public function test_that_complete_list_and_exact_get_share_entries_without_opening_or_rehashing_files(): void
    {
        $first = SkillRevision::create();
        $second = SkillRevision::create('v2');
        $streams = $this->mock(StreamFactoryInterface::class);
        $streams->shouldNotReceive('createStream');
        $policy = new DiscoveryAvailability();
        $provider = new McpSkillResources([$second, $first], $policy, $streams);
        [$skills, $responder] = $this->composition($provider, new McpSkillDiscoveryLimits(pageSize: 1));
        self::assertSame(['skills/list', 'skills/get'], $skills->methods());
        self::assertSame([], $skills->mirrorDeclarations());
        $discovery = $this->response($responder, 'server/discover');
        self::assertSame([], $policy->seen);
        self::assertSame('{}', json_encode(json_decode($responder->respond($this->wire('server/discover'))->toJson())->result->capabilities->extensions->{'io.modelcontextprotocol/skills'}));
        self::assertArrayHasKey('resources', $discovery['result']['capabilities']);
        $get = $this->response($responder, 'skills/get', ['uri' => $second->entry()->get('uri')])['result'];
        self::assertSame($this->entry($second->entry()), $get['skill']);
        self::assertArrayNotHasKey('nextCursor', $get);
        $page = $this->response($responder, 'skills/list')['result'];
        self::assertSame([$this->entry($first->entry())], $page['skills']);
        self::assertSame(0, $page['ttlMs']);
        self::assertSame('private', $page['cacheScope']);
        self::assertSame('complete', $page['resultType']);
        self::assertSame($page, $this->response($responder, 'skills/list', ['cursor' => ''])['result']);
        $last = $this->response($responder, 'skills/list', ['cursor' => $page['nextCursor']])['result'];
        self::assertSame([$get['skill']], $last['skills']);
        self::assertArrayNotHasKey('nextCursor', $last);
        self::assertSame($page['skills'][0]['frontmatter']['name'], $last['skills'][0]['frontmatter']['name']);
        self::assertCount(7, $last['skills'][0]['resources']);
        self::assertSame(['values' => [true, null, 1, '雪'], 'empty' => [], 'list' => []], $get['skill']['frontmatter']['unknown']);
        self::assertSame([$first->entry()->get('uri'), $second->entry()->get('uri')], array_keys($provider->entries()));
        self::assertSame($first->entry(), $provider->findEntry($first->entry()->get('uri')));
        self::assertNull($provider->findEntry('skill://catalog/v1/work/references/guide.md'));
        // Direct semantic callers use the same capability, without a second endpoint or implementation.
        $direct = $skills->handle((new McpRequestDecoder())->decode($this->wire('skills/list')))->toArray();
        self::assertSame($first->entry(), $direct['skills'][0]);
        self::assertArrayNotHasKey('_meta', $direct);
        $directGet = $skills->handle((new McpRequestDecoder())->decode($this->wire('skills/get', ['uri' => $first->entry()->get('uri')])))->toArray();
        self::assertArrayNotHasKey('_meta', $directGet);
    }

    public function test_that_filtering_precedes_pagination_and_rechecks_direct_and_historical_targets(): void
    {
        $policy = new DiscoveryAvailability();
        $revisions = [SkillRevision::create(), SkillRevision::create('v2'), SkillRevision::create('v3')];
        $provider = new McpSkillResources($revisions, $policy, new HttpFactory());
        [, $responder] = $this->composition($provider, new McpSkillDiscoveryLimits(pageSize: 1));
        $policy->denied = [$revisions[0]->entry()->get('uri')];
        $page = $this->response($responder, 'skills/list')['result'];
        self::assertSame($revisions[1]->entry()->get('uri'), $page['skills'][0]['uri']);
        self::assertSame($revisions[2]->entry()->get('uri'), $this->response($responder, 'skills/list', ['cursor' => $page['nextCursor']])['result']['skills'][0]['uri']);
        // A changed concealed entry cannot invalidate an otherwise unchanged visible catalog.
        $changed = new McpSkillResources([SkillRevision::create('hidden'), ...array_slice($revisions, 1)], $policy, new HttpFactory());
        $policy->denied[] = 'skill://catalog/hidden/work/SKILL.md';
        [, $other] = $this->composition($changed, new McpSkillDiscoveryLimits(pageSize: 1));
        self::assertArrayHasKey('result', $this->response($other, 'skills/list', ['cursor' => $page['nextCursor']]));
        $unknown = $this->response($responder, 'skills/get', ['uri' => 'skill://catalog/missing/work/SKILL.md']);
        self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $unknown['error']);
        self::assertSame($unknown, $this->response($responder, 'skills/get', ['uri' => $policy->denied[0]]));
        $policy->denied = array_map(fn($revision) => $revision->entry()->get('uri'), $revisions);
        self::assertSame([], $this->response($responder, 'skills/list')['result']['skills']);
        self::assertSame(-32602, $this->response($responder, 'skills/list', ['cursor' => $page['nextCursor']])['error']['code']);
        foreach ($revisions as $revision) {
            self::assertSame($unknown, $this->response($responder, 'skills/get', ['uri' => $revision->entry()->get('uri')]));
            foreach (array_keys($revision->resources()) as $uri) {
                self::assertSame(-32602, $this->response($responder, 'resources/read', ['uri' => $uri])['error']['code']);
            }
        }
        $policy->denied = [];
        self::assertArrayHasKey('result', $this->response($responder, 'skills/get', ['uri' => $revisions[0]->entry()->get('uri')]));
    }

    public function test_that_general_resource_denial_conceals_the_entire_entry_without_redacting_a_manifest(): void
    {
        $policy = new class implements McpResourceAvailability {
            public bool $allowed = true;
            public function isAvailable(McpResourceInfo $resource): bool {
                return $this->allowed || !str_ends_with($resource->uri(), '/references/guide.md');
            }
        };
        $provider = new McpSkillResources([SkillRevision::create()], new DiscoveryAvailability(), new HttpFactory());
        $resources = new McpResourceDiscovery([$provider], $policy, str_repeat('k', 32), 'scope', readLimits: new McpResourceReadLimits());
        $skills = new McpSkillDiscovery($provider, $resources, str_repeat('k', 32), 'scope');
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('server', '1'), [$skills, $resources]));
        self::assertCount(1, $this->response($responder, 'skills/list')['result']['skills']);
        $policy->allowed = false;
        self::assertSame([], $this->response($responder, 'skills/list')['result']['skills']);
        self::assertSame(-32602, $this->response($responder, 'skills/get', ['uri' => 'skill://catalog/v1/work/SKILL.md'])['error']['code']);
        self::assertSame(-32602, $this->response($responder, 'resources/read', ['uri' => 'skill://catalog/v1/work/references/guide.md'])['error']['code']);
        $policy->allowed = true;
        self::assertCount(7, $this->response($responder, 'skills/get', ['uri' => 'skill://catalog/v1/work/SKILL.md'])['result']['skill']['resources']);
    }

    public function test_that_empty_catalog_and_safe_cache_overrides_produce_complete_results(): void
    {
        $provider = new McpSkillResources([], new DiscoveryAvailability(), new HttpFactory());
        [$skills, $responder] = $this->composition($provider, ttl: 9007199254740991, scope: 'public');
        $list = $this->response($responder, 'skills/list')['result'];
        self::assertSame([], $list['skills']);
        self::assertSame(9007199254740991, $list['ttlMs']);
        self::assertSame('public', $list['cacheScope']);
        self::assertArrayNotHasKey('nextCursor', $list);
        self::assertSame(-32602, $this->response($responder, 'skills/get', ['uri' => 'skill://catalog/v1/work/SKILL.md'])['error']['code']);
        $this->expectException(McpProtocolException::class);
        $skills->validate((new McpRequestDecoder())->decode($this->wire('other/method')));
    }

    #[DataProvider('invalidRequests')]
    public function test_that_invalid_requests_and_inexact_uris_do_not_disclose_entries(string $method, array $parameters): void
    {
        $policy = new DiscoveryAvailability();
        [, $responder] = $this->composition(new McpSkillResources([SkillRevision::create()], $policy, new HttpFactory()));
        $response = $this->response($responder, $method, $parameters);
        self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $response['error']);
        self::assertSame([], $policy->seen);
    }

    public static function invalidRequests(): iterable
    {
        foreach ([['cursor' => null], ['cursor' => 1], ['cursor' => false], ['cursor' => []], ['cursor' => 'x'], ['cursor' => str_repeat('!', 48)], ['cursor' => str_repeat('a', 49)], ['extra' => 1]] as $params) { yield ['skills/list', $params]; }
        foreach ([[], ['uri' => null], ['uri' => 1], ['uri' => []], ['uri' => ''], ['uri' => '/relative'], ['uri' => 'http://[bad'], ['uri' => 'x:'.str_repeat('a', 8192)], ['uri' => 'skill://catalog/v1/work/SKILL.md', 'extra' => 1], ['uri' => 'skill://catalog/v1/work/%53KILL.md'], ['uri' => 'skill://catalog/v1/work/../work/SKILL.md'], ['uri' => 'skill://catalog/v1/work/references/guide.md'], ['uri' => 'skill://catalog/v1/work/SKILL.md#fragment']] as $params) { yield ['skills/get', $params]; }
    }

    public function test_that_cursors_reject_tampering_scope_key_page_budget_and_visible_catalog_changes(): void
    {
        $provider = new McpSkillResources([SkillRevision::create(), SkillRevision::create('v2'), SkillRevision::create('v3')], new DiscoveryAvailability(), new HttpFactory());
        [, $responder] = $this->composition($provider, new McpSkillDiscoveryLimits(pageSize: 1));
        $cursor = $this->response($responder, 'skills/list')['result']['nextCursor'];
        foreach ([str_repeat('A', 48), strtr(base64_encode(pack('N', 999).str_repeat('x', 32)), '+/', '-_'), substr($cursor, 0, -1).($cursor[-1] === 'A' ? 'B' : 'A')] as $bad) {
            self::assertSame(-32602, $this->response($responder, 'skills/list', ['cursor' => $bad])['error']['code']);
        }
        foreach ([['cursorScope' => 'other'], ['key' => str_repeat('b', 32)], ['limits' => new McpSkillDiscoveryLimits(pageSize: 2)], ['limits' => new McpSkillDiscoveryLimits(pageSize: 1, maxResultBytes: 9000000)]] as $options) {
            $options += ['limits' => new McpSkillDiscoveryLimits(pageSize: 1)];
            [, $other] = $this->composition($provider, ...$options);
            self::assertSame(-32602, $this->response($other, 'skills/list', ['cursor' => $cursor])['error']['code']);
        }
        $changed = new McpSkillResources([SkillRevision::create(), SkillRevision::create('v2'), SkillRevision::create('v4')], new DiscoveryAvailability(), new HttpFactory());
        [, $other] = $this->composition($changed, new McpSkillDiscoveryLimits(pageSize: 1));
        self::assertSame(-32602, $this->response($other, 'skills/list', ['cursor' => $cursor])['error']['code']);
        self::assertArrayHasKey('result', $this->response($other, 'skills/list', ['cursor' => '']));
    }

    public function test_that_encoded_page_budget_preserves_atomic_entries_and_allows_non_count_aligned_offsets(): void
    {
        $revisions = [SkillRevision::create(), SkillRevision::create('v2'), SkillRevision::create('v3')];
        $provider = new McpSkillResources($revisions, new DiscoveryAvailability(), new HttpFactory());
        $size = max(array_map(fn($revision) => strlen($revision->entry()->toString()), $revisions));
        $limits = new McpSkillDiscoveryLimits(pageSize: 100, maxEntryBytes: $size, maxResultBytes: $size + 512);
        [, $responder] = $this->composition($provider, $limits);
        $params = [];
        foreach ($revisions as $index => $revision) {
            $page = $this->response($responder, 'skills/list', $params)['result'];
            self::assertSame([$this->entry($revision->entry())], $page['skills']);
            self::assertLessThanOrEqual($limits->maxResultBytes, strlen(json_encode($page)));
            self::assertSame($page['skills'][0], $this->response($responder, 'skills/get', ['uri' => $revision->entry()->get('uri')])['result']['skill']);
            if ($index < 2) { $params = ['cursor' => $page['nextCursor']]; }
            else { self::assertArrayNotHasKey('nextCursor', $page); }
        }
    }

    #[DataProvider('floatCatalogSizes')]
    public function test_that_float_entries_traverse_tight_encoded_pages_with_actual_metadata(int $count): void
    {
        $bytes = "---\nname: work\ndescription: Float metadata\nvalues: ["
            .implode(', ', array_fill(0, 200, '1.0'))."]\n---\n";
        $revisions = [];
        for ($index = 1; $index <= $count; ++$index) {
            $revisions[] = McpSkillRevision::fromFiles('skill://catalog/v'.$index.'/work/SKILL.md', [
                McpSkillFile::fromBytes('SKILL.md', $bytes)
            ], new SymfonyMcpSkillFrontmatterParser());
        }
        $streams = $this->mock(StreamFactoryInterface::class);
        $streams->shouldNotReceive('createStream');
        $provider = new McpSkillResources($revisions, new DiscoveryAvailability(), $streams);
        $size = strlen($revisions[0]->entry()->toString());
        $limits = new McpSkillDiscoveryLimits(pageSize: 100, maxEntryBytes: $size, maxResultBytes: $size + 256);
        [, $responder] = $this->composition($provider, $limits, server: new McpServerInfo(str_repeat('s', 300), '1'));
        $params = [];
        $cursors = [];
        foreach ($revisions as $index => $revision) {
            self::assertSame(array_fill(0, 200, 1.0), $revision->entry()->get('frontmatter')->get('values'));
            $get = $this->response($responder, 'skills/get', ['uri' => $revision->entry()->get('uri')]);
            self::assertArrayHasKey('result', $get);
            $expected = json_decode(json_encode($revision->entry(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($expected, $get['result']['skill']);
            // Independently establish that a complete entry plus a continuation fits the actual result budget.
            $permitted = $get['result'];
            unset($permitted['skill']);
            $permitted['skills'] = [$expected];
            $permitted['nextCursor'] = str_repeat('x', 48);
            self::assertLessThanOrEqual($limits->maxResultBytes, strlen(json_encode($permitted, JSON_THROW_ON_ERROR)));
            $response = $this->response($responder, 'skills/list', $params);
            self::assertArrayHasKey('result', $response);
            $page = $response['result'];
            self::assertSame([$expected], $page['skills']);
            self::assertSame($get['result']['_meta'], $page['_meta']);
            self::assertSame('complete', $page['resultType']);
            self::assertSame('private', $page['cacheScope']);
            self::assertSame(0, $page['ttlMs']);
            self::assertLessThanOrEqual($limits->maxResultBytes, strlen(json_encode($page, JSON_THROW_ON_ERROR)));
            if ($index + 1 < $count) {
                self::assertArrayHasKey('nextCursor', $page);
                self::assertNotContains($page['nextCursor'], $cursors);
                $cursors[] = $page['nextCursor'];
                $params = ['cursor' => $page['nextCursor']];
            } else {
                self::assertArrayNotHasKey('nextCursor', $page);
            }
        }
    }

    public static function floatCatalogSizes(): iterable
    {
        yield 'single entry terminates' => [1];
        yield 'byte-limited continuation traverses every entry' => [3];
    }

    public function test_that_actual_central_metadata_is_checked_before_disclosing_any_entry(): void
    {
        $revision = SkillRevision::create();
        $provider = new McpSkillResources([$revision], new DiscoveryAvailability(), new HttpFactory());
        $size = strlen($revision->entry()->toString());
        $limits = new McpSkillDiscoveryLimits(maxEntryBytes: $size, maxResultBytes: $size + 256);
        [$skills, $responder] = $this->composition($provider, $limits, server: new McpServerInfo(str_repeat('x', 1000), '1'));
        foreach (['skills/list' => [], 'skills/get' => ['uri' => $revision->entry()->get('uri')]] as $method => $params) {
            self::assertSame(-32603, $this->response($responder, $method, $params)['error']['code']);
        }
        // Sharing the same capability with a smaller responder must not retain the previous metadata.
        $small = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('server', '1'), [$skills, $skills->resourceDiscovery()]));
        self::assertArrayHasKey('result', $this->response($small, 'skills/list'));
    }

    public function test_that_resource_budget_mismatch_or_conflicting_ownership_fails_even_for_concealed_skills(): void
    {
        $revision = SkillRevision::create();
        $policy = new DiscoveryAvailability(); $policy->denied = [$revision->entry()->get('uri')];
        $provider = new McpSkillResources([$revision], $policy, new HttpFactory());
        foreach ([new McpResourceReadLimits(maxContentBytes: 1), null] as $readLimits) {
            $extras = $readLimits === null ? [new ReadableResources([$revision->entry()->get('uri') => ['bytes' => 'collision']])] : [];
            $resources = $this->resources($provider, readLimits: $readLimits ?? new McpResourceReadLimits(), extras: $extras);
            try { new McpSkillDiscovery($provider, $resources, str_repeat('k', 32), 'scope'); self::fail('Unservable catalog accepted.'); }
            catch (DomainException) { self::assertSame([], $policy->seen); }
        }
        $resources = $this->resources($provider, readLimits: new McpResourceReadLimits(maxContentBytes: 1024, maxResultBytes: 6500));
        $skills = new McpSkillDiscovery($provider, $resources, str_repeat('k', 32), 'scope');
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo(str_repeat('s', 1024), '1'), [$skills, $resources]));
        self::assertSame(-32603, $this->response($responder, 'skills/list')['error']['code']);
        self::assertSame([], $policy->seen);
    }

    public function test_that_provider_and_policy_failures_are_sanitized_not_treated_as_public_protocol_errors(): void
    {
        $policy = new DiscoveryAvailability();
        $provider = new McpSkillResources([SkillRevision::create()], $policy, new HttpFactory());
        $other = new ReadableResources([]);
        $resources = $this->resources($provider, extras: [$other]);
        $skills = new McpSkillDiscovery($provider, $resources, str_repeat('k', 32), 'scope');
        $diagnostics = new class implements McpDiagnostics { public array $failures = []; public function record(Throwable $failure): void { $this->failures[] = $failure; } };
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('server', '1'), [$resources, $skills]), diagnostics: $diagnostics);
        foreach ([new RuntimeException('private secret'), new McpProtocolException(McpProtocolError::invalidParams(), 99)] as $failure) {
            $policy->failure = $failure;
            foreach (['skills/list' => [], 'skills/get' => ['uri' => 'skill://catalog/v1/work/SKILL.md']] as $method => $params) {
                self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $this->response($responder, $method, $params)['error']);
            }
        }
        self::assertCount(4, $diagnostics->failures);
    }

    public function test_that_runtime_catalog_failure_cannot_supply_a_public_protocol_error(): void
    {
        $provider = new McpSkillResources([SkillRevision::create()], new DiscoveryAvailability(), new HttpFactory());
        $other = new class implements McpReadableResourceProvider {
            public bool $fail = false;
            public function resources(): iterable {
                if ($this->fail) { throw new McpProtocolException(McpProtocolError::invalidParams(), 99); }
                return [];
            }
            public function find(string $uri): ?McpResourceInfo { return null; }
            public function open(McpResourceInfo $resource): McpResourceContent { throw new RuntimeException('No content'); }
        };
        $resources = $this->resources($provider, extras: [$other]);
        $skills = new McpSkillDiscovery($provider, $resources, str_repeat('k', 32), 'scope');
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('server', '1'), [$resources, $skills]));
        $other->fail = true;
        self::assertSame(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => -32603, 'message' => 'Internal error.']], $this->response($responder, 'skills/list'));
    }

    #[DataProvider('invalidComposition')]
    public function test_that_invalid_composition_is_rejected(string $case): void
    {
        $provider = new McpSkillResources([SkillRevision::create(), SkillRevision::create('v2')], new DiscoveryAvailability(), new HttpFactory());
        $resources = $this->resources($provider);
        if ($case === 'discovery-only') { $resources = new McpResourceDiscovery([$provider], $this->general(), str_repeat('k', 32), 'scope'); }
        if ($case === 'other-provider') { $resources = $this->resources(new McpSkillResources([], new DiscoveryAvailability(), new HttpFactory())); }
        $limits = match ($case) {
            'count' => new McpSkillDiscoveryLimits(maxEntries: 1),
            'entry' => new McpSkillDiscoveryLimits(maxEntryBytes: 1),
            'uri' => new McpSkillDiscoveryLimits(maxUriBytes: 1),
            default => new McpSkillDiscoveryLimits()
        };
        $this->expectException(DomainException::class);
        new McpSkillDiscovery($provider, $resources,
            match ($case) { 'key-short' => str_repeat('k', 31), 'key-long' => str_repeat('k', 4097), default => str_repeat('k', 32) },
            match ($case) { 'scope-empty' => '', 'scope-long' => str_repeat('s', 129), default => 'scope' },
            $limits,
            match ($case) { 'negative-ttl' => -1, 'unsafe-ttl' => 9007199254740992, default => 0 },
            $case === 'cache' ? 'shared' : 'private');
    }

    public static function invalidComposition(): iterable
    {
        foreach (['discovery-only', 'other-provider', 'count', 'entry', 'uri', 'key-short', 'key-long', 'scope-empty', 'scope-long', 'negative-ttl', 'unsafe-ttl', 'cache'] as $case) { yield [$case]; }
    }

    #[DataProvider('invalidLimits')]
    public function test_that_unsafe_discovery_limits_are_rejected(array $limits): void
    {
        $this->expectException(DomainException::class);
        new McpSkillDiscoveryLimits(...$limits);
    }

    public static function invalidLimits(): iterable
    {
        foreach (['pageSize' => [0, 1001], 'maxEntries' => [0, 1000001], 'maxEntryBytes' => [0, 16777217], 'maxResultBytes' => [1, 67108865], 'maxUriBytes' => [0, 65537]] as $name => $values) {
            foreach ($values as $value) { yield [[$name => $value]]; }
        }
    }

    private function composition(McpSkillResources $provider, McpSkillDiscoveryLimits $limits = new McpSkillDiscoveryLimits(), int $ttl = 0, string $scope = 'private', string $key = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk', string $cursorScope = 'scope', ?McpServerInfo $server = null): array
    {
        $resources = $this->resources($provider);
        $skills = new McpSkillDiscovery($provider, $resources, $key, $cursorScope, $limits, $ttl, $scope);
        return [$skills, new McpResponder(new McpCapabilityRegistry($server ?? new McpServerInfo('server', '1'), [$skills, $resources]))];
    }

    private function resources(McpSkillResources $provider, McpResourceReadLimits $readLimits = new McpResourceReadLimits(), array $extras = []): McpResourceDiscovery
    {
        return new McpResourceDiscovery([$provider, ...$extras], $this->general(), str_repeat('k', 32), 'scope', readLimits: $readLimits);
    }

    private function general(): McpResourceAvailability
    {
        return new class implements McpResourceAvailability { public function isAvailable(McpResourceInfo $resource): bool { return true; } };
    }

    private function entry(StrictJson $entry): array { return json_decode($entry->toString(), true, flags: JSON_THROW_ON_ERROR); }
    private function response(McpResponder $responder, string $method, array $params = []): array { return json_decode($responder->respond($this->wire($method, $params))->toJson(), true, flags: JSON_THROW_ON_ERROR); }
    private function wire(string $method, array $params = []): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => [...$params, '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR);
    }
}

final class DiscoveryAvailability implements McpSkillAvailability
{
    public array $denied = [];
    public array $seen = [];
    public ?Throwable $failure = null;
    public function isAvailable(StrictJson $entry): bool
    {
        $this->seen[] = $entry;
        if ($this->failure !== null) { throw $this->failure; }
        return !in_array($entry->get('uri'), $this->denied, true);
    }
}
