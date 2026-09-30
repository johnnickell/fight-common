<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Skill;

use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillAvailability;
use Fight\Common\Application\Mcp\Skill\McpSkillFile;
use Fight\Common\Application\Mcp\Skill\McpSkillFrontmatterParser;
use Fight\Common\Application\Mcp\Skill\McpSkillLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillResources;
use Fight\Common\Application\Mcp\Skill\McpSkillRevision;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\StreamFactoryInterface;

#[CoversClass(McpSkillResources::class)]
#[CoversClass(McpResourceDiscovery::class)]
final class McpSkillResourcesTest extends UnitTestCase
{
    private const URI = 'skill://host/v1/work/SKILL.md';

    public function test_that_provider_serves_only_selected_immutable_files_and_never_opens_during_metadata_reads(): void
    {
        $revision = $this->revision();
        $streams = $this->mock(StreamFactoryInterface::class);
        $streams->shouldReceive('createStream')->once()->with("\0\xff")->andReturn(Utils::streamFor("\0\xff"));
        $streams->shouldReceive('createStream')->once()->with("---\nname: work\n---\n")->andReturn(Utils::streamFor("---\nname: work\n---\n"));
        $policy = new SkillAvailability();
        $provider = new McpSkillResources([$revision], $policy, $streams);
        self::assertSame(array_values($revision->resources()), $provider->resources());
        self::assertSame([], $policy->seen);
        self::assertNull($provider->find('skill://host/v1/work/%53KILL.md'));
        self::assertFalse($provider->isAvailable(McpResourceInfo::fromArray(['uri' => 'test:/unknown', 'name' => 'x'])));
        foreach ([self::URI => "---\nname: work\n---\n", 'skill://host/v1/work/binary' => "\0\xff"] as $uri => $bytes) {
            $info = $provider->find($uri);
            $content = $provider->open($info)->consume($info, new McpResourceReadLimits());
            self::assertSame($bytes, $content['text'] ?? base64_decode($content['blob'], true));
        }
        self::assertSame([$revision->entry(), $revision->entry()], $policy->seen);
    }

    public function test_that_provider_denial_prevents_stream_allocation_even_for_direct_open(): void
    {
        $policy = new SkillAvailability(); $policy->allowed = false;
        $streams = $this->mock(StreamFactoryInterface::class); $streams->shouldNotReceive('createStream');
        $provider = new McpSkillResources([$this->revision()], $policy, $streams);
        self::assertNotNull($provider->find(self::URI)); // Internal ownership still detects concealed collisions.
        self::assertFalse($provider->isAvailable($provider->find(self::URI)));
        $this->expectException(DomainException::class);
        $provider->open($provider->find(self::URI));
    }

    public function test_that_provider_rejects_forged_descriptor_before_stream_allocation(): void
    {
        $streams = $this->mock(StreamFactoryInterface::class); $streams->shouldNotReceive('createStream');
        $provider = new McpSkillResources([$this->revision()], new SkillAvailability(), $streams);
        $this->expectException(DomainException::class);
        $provider->open(McpResourceInfo::fromArray(['uri' => self::URI, 'name' => 'forged', 'size' => 99]));
    }

    public function test_that_shared_dispatch_enforces_whole_skill_policy_even_with_permissive_resource_policy(): void
    {
        $policy = new SkillAvailability();
        $streamCount = 0;
        $streams = $this->mock(StreamFactoryInterface::class);
        $streams->shouldReceive('createStream')->andReturnUsing(function ($bytes) use (&$streamCount) { ++$streamCount; return Utils::streamFor($bytes); });
        $old = $this->revision(); $new = $this->revision('v2');
        $provider = new McpSkillResources([$new, $old], $policy, $streams);
        $general = new class implements McpResourceAvailability {
            public bool $allowed = true;
            public function isAvailable(McpResourceInfo $resource): bool { return $this->allowed; }
        };
        $capability = new McpResourceDiscovery([$provider], $general, str_repeat('k', 32), 'skills', readLimits: new McpResourceReadLimits());
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('skills', '1'), [$capability]));
        $list = $responder->respond($this->wire('resources/list'))->toArray();
        self::assertCount(4, $list['result']['resources']);
        self::assertSame(0, $streamCount);
        self::assertSame('private', $list['result']['cacheScope']);
        self::assertSame(0, $list['result']['ttlMs']);
        self::assertSame(['resources' => ['subscribe' => false, 'listChanged' => false]], json_decode(json_encode($responder->respond($this->wire('server/discover'))->toArray()), true)['result']['capabilities']);
        self::assertSame("---\nname: work\n---\n", $responder->respond($this->wire('resources/read', ['uri' => self::URI]))->toArray()['result']['contents'][0]['text']);
        self::assertSame(1, $streamCount);
        $policy->allowed = false;
        self::assertSame([], $responder->respond($this->wire('resources/list'))->toArray()['result']['resources']);
        $unknown = $responder->respond($this->wire('resources/read', ['uri' => 'test:/unknown']))->toArray();
        foreach (array_keys($old->resources() + $new->resources()) as $uri) {
            self::assertSame($unknown, $responder->respond($this->wire('resources/read', ['uri' => $uri]))->toArray());
        }
        self::assertSame(-32602, $unknown['error']['code']);
        self::assertSame(1, $streamCount);
        $policy->allowed = true; $general->allowed = false;
        self::assertSame($unknown, $responder->respond($this->wire('resources/read', ['uri' => self::URI]))->toArray());
        self::assertSame([], $responder->respond($this->wire('resources/list'))->toArray()['result']['resources']);
        self::assertSame(1, $streamCount);
        $general->allowed = true;
        self::assertArrayHasKey('result', $responder->respond($this->wire('resources/read', ['uri' => self::URI]))->toArray());
        self::assertSame(2, $streamCount);

        // Even concealed ownership conflicts must fail, not fall through to a second provider.
        $policy->allowed = false;
        $duplicate = new McpSkillResources([$old], new SkillAvailability(), $streams);
        $conflict = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('skills', '1'), [new McpResourceDiscovery([$provider, $duplicate], $general, str_repeat('k', 32), 'skills', readLimits: new McpResourceReadLimits())]));
        foreach (['resources/list' => [], 'resources/read' => ['uri' => self::URI]] as $method => $params) {
            self::assertSame(-32603, $conflict->respond($this->wire($method, $params))->errorCode());
        }
        self::assertSame(2, $streamCount);
    }

    public function test_that_changed_stream_factory_bytes_fail_integrity_without_successful_disclosure(): void
    {
        $streams = $this->mock(StreamFactoryInterface::class);
        $body = Utils::streamFor("\1\xff");
        $streams->shouldReceive('createStream')->with("\0\xff")->once()->andReturn($body);
        $provider = new McpSkillResources([$this->revision()], new SkillAvailability(), $streams);
        $info = $provider->find('skill://host/v1/work/binary');
        try { $provider->open($info)->consume($info, new McpResourceReadLimits()); self::fail('Mutated bytes accepted.'); }
        catch (DomainException) { self::assertFalse($body->isReadable()); }
    }

    public function test_that_empty_and_exactly_bounded_catalogs_are_supported(): void
    {
        $provider = new McpSkillResources([], new SkillAvailability(), new HttpFactory(), 1);
        self::assertSame([], $provider->resources());
        self::assertNull($provider->find(self::URI));
        self::assertCount(2, (new McpSkillResources([$this->revision()], new SkillAvailability(), new HttpFactory(), 2))->resources());
    }

    #[DataProvider('invalidCatalogs')]
    public function test_that_invalid_catalogs_are_rejected_before_exposure(string $case): void
    {
        $revision = $this->revision();
        $revisions = match ($case) { 'duplicate' => [$revision, $revision], 'invalid' => ['wrong'], default => [$revision] };
        $limit = match ($case) { 'small' => 1, 'zero' => 0, 'large' => 1000001, default => 10000 };
        $this->expectException(DomainException::class);
        new McpSkillResources($revisions, new SkillAvailability(), new HttpFactory(), $limit);
    }

    public static function invalidCatalogs(): iterable
    {
        foreach (['duplicate', 'invalid', 'small', 'zero', 'large'] as $case) { yield [$case]; }
    }

    private function revision(string $revision = 'v1'): McpSkillRevision
    {
        $parser = new class implements McpSkillFrontmatterParser {
            public function parse(string $yaml, McpSkillLimits $limits): StrictJson { return StrictJson::fromObject(['name' => 'work', 'description' => 'Test']); }
        };
        return McpSkillRevision::fromFiles('skill://host/'.$revision.'/work/SKILL.md', [McpSkillFile::fromBytes('SKILL.md', "---\nname: work\n---\n"), McpSkillFile::fromBytes('binary', "\0\xff", text: false)], $parser);
    }

    private function wire(string $method, array $parameters = []): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => [...$parameters, '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR);
    }
}

final class SkillAvailability implements McpSkillAvailability
{
    public bool $allowed = true;
    public array $seen = [];
    public function isAvailable(StrictJson $entry): bool { $this->seen[] = $entry; return $this->allowed; }
}
