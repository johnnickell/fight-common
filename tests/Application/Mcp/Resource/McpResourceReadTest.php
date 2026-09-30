<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Resource;

use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Resource\McpReadableResourceProvider;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceContent;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceProvider;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(McpResourceDiscovery::class)]
final class McpResourceReadTest extends UnitTestCase
{
    public function test_that_opt_in_reads_share_one_owner_and_do_not_break_metadata_only_composition(): void
    {
        $resources = $this->capability([]);
        self::assertSame(['resources/list', 'resources/templates/list', 'resources/read'], $resources->methods());
        $registry = new McpCapabilityRegistry(new McpServerInfo('Reads', '1'), [$resources]);
        self::assertSame($resources, $registry->capabilityFor('resources/read'));
        self::assertSame(['resources' => ['subscribe' => false, 'listChanged' => false]], $registry->advertisedCapabilities());
        $legacy = $this->mock(McpResourceProvider::class);
        $legacy->shouldNotReceive('resources');
        $discovery = new McpResourceDiscovery([$legacy], new ReadAvailability(), str_repeat('k', 32), 'scope');
        self::assertNotContains('resources/read', $discovery->methods());
        $this->expectException(DomainException::class);
        $this->capability([$legacy]);
    }

    public function test_that_direct_unlisted_reads_route_to_the_exact_owner_without_enumeration_or_prefetch(): void
    {
        $first = new ReadProvider(['test:/a' => 'A']);
        $second = new ReadProvider(['test:/unlisted' => "雪\r\n"], listed: false);
        foreach ([[$first, $second], [$second, $first]] as $providers) {
            $resources = $this->capability($providers);
            $read = $resources->handle($this->request('test:/unlisted'))->toArray();
            self::assertSame(['resultType' => 'complete', 'contents' => [['uri' => 'test:/unlisted', 'mimeType' => 'text/plain', 'text' => "雪\r\n"]], 'ttlMs' => 0, 'cacheScope' => 'private'], $read);
        }
        self::assertSame([], $first->opened);
        self::assertSame(['test:/unlisted', 'test:/unlisted'], $second->opened);
        self::assertSame(0, $first->enumerations + $second->enumerations);
        self::assertSame(['test:/unlisted', 'test:/unlisted'], $first->lookedUp);
    }

    public function test_that_private_and_public_cache_hints_never_reuse_content_or_authority(): void
    {
        foreach (['private', 'public'] as $scope) {
            $provider = new ReadProvider(['test:/a' => 'first']);
            $availability = new ReadAvailability();
            $resources = new McpResourceDiscovery([$provider], $availability, str_repeat('k', 32), 'scope', ttlMs: 500, cacheScope: $scope, readLimits: new McpResourceReadLimits());
            $first = $resources->handle($this->request('test:/a'))->toArray();
            self::assertSame(500, $first['ttlMs']);
            self::assertSame($scope, $first['cacheScope']);
            $provider->entries['test:/a'] = 'changed';
            self::assertSame('changed', $resources->handle($this->request('test:/a'))->toArray()['contents'][0]['text']);
            $availability->allowed = false;
            $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Reads', '1'), [$resources]));
            $denied = $responder->respond($this->wire('resources/read', ['uri' => 'test:/a']))->toArray();
            self::assertSame($denied, $responder->respond($this->wire('resources/read', ['uri' => 'test:/missing']))->toArray());
            self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $denied['error']);
            self::assertSame(['test:/a', 'test:/a'], $provider->opened);
            $anotherCaller = $this->capability([$provider], new ReadAvailability());
            self::assertSame('changed', $anotherCaller->handle($this->request('test:/a'))->toArray()['contents'][0]['text']);
        }
    }

    #[DataProvider('invalidParameters')]
    public function test_that_invalid_requests_do_not_touch_providers(array $parameters): void
    {
        $provider = $this->mock(McpReadableResourceProvider::class);
        $provider->shouldNotReceive('find');
        $provider->shouldNotReceive('open');
        $this->expectException(McpProtocolException::class);
        $this->capability([$provider])->handle($this->decode('resources/read', $parameters));
    }

    public static function invalidParameters(): iterable
    {
        yield [[]];
        foreach ([null, 1, [], '', '/a', 'bad space:/a', 'test:/%zz', 'test:/'.str_repeat('a', 4096)] as $uri) { yield [['uri' => $uri]]; }
        yield [['uri' => 'test:/a', 'cursor' => '']];
    }

    #[DataProvider('nonIdenticalUris')]
    public function test_that_traversal_and_encoded_aliases_cannot_escape_exact_registration(string $uri): void
    {
        $provider = new ReadProvider(['file:///safe/a' => 'protected']);
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Reads', '1'), [$this->capability([$provider])]));
        self::assertSame(-32602, $responder->respond($this->wire('resources/read', ['uri' => $uri]))->errorCode());
        self::assertSame([], $provider->opened);
        self::assertSame([$uri], $provider->lookedUp);
    }

    public static function nonIdenticalUris(): iterable
    {
        foreach (['file:///safe/../a', 'file:///safe/%2e%2e/a', 'file:///safe/%252e%252e/a', 'file:///safe/%61', 'file:///safe%2fa', 'file:///safe/a?x=1', 'file:///safe/a#x', 'file:///etc/passwd', 'https://127.0.0.1/private', 'file:///safe/%5c..%5ca', 'FILE:///safe/a'] as $uri) { yield [$uri]; }
    }

    public function test_that_registered_encoded_identity_is_not_decoded_into_a_different_target(): void
    {
        $provider = new ReadProvider(['test:/a' => 'literal', 'test:/%61' => 'encoded']);
        self::assertSame('encoded', $this->capability([$provider])->handle($this->request('test:/%61'))->toArray()['contents'][0]['text']);
        self::assertSame(['test:/%61'], $provider->opened);
    }

    public function test_that_ambiguous_ownership_is_rejected_before_availability_even_for_hidden_resources(): void
    {
        $one = new ReadProvider(['test:/a' => 'first']);
        $two = new ReadProvider(['test:/a' => 'second']);
        $availability = $this->mock(McpResourceAvailability::class);
        $availability->shouldNotReceive('isAvailable');
        foreach ([[$one, $two], [$two, $one]] as $providers) {
            try { $this->capability($providers, $availability)->handle($this->request('test:/a')); self::fail('Ambiguous read accepted.'); }
            catch (DomainException) { self::assertSame([], $one->opened); self::assertSame([], $two->opened); }
        }
    }

    public function test_that_a_provider_cannot_normalize_lookup_into_another_registered_target(): void
    {
        $provider = $this->mock(McpReadableResourceProvider::class);
        $provider->shouldReceive('find')->with('file:///safe/../a')->once()->andReturn(McpResourceInfo::fromArray(['uri' => 'file:///a', 'name' => 'wrong']));
        $provider->shouldNotReceive('open');
        $this->expectException(DomainException::class);
        $this->capability([$provider])->handle($this->request('file:///safe/../a'));
    }

    public function test_that_oversized_descriptors_cannot_be_listed_or_opened_in_read_composition(): void
    {
        $provider = new ReadProvider(['test:/a' => 'ab']);
        $resources = $this->capability([$provider], readLimits: new McpResourceReadLimits(1, 1024));
        foreach ([$this->request('test:/a'), $this->decode('resources/list')] as $request) {
            try { $resources->handle($request); self::fail('Unservable descriptor accepted.'); }
            catch (DomainException) { self::assertSame([], $provider->opened); }
        }
        $provider->entries = ['test:/'.str_repeat('a', 100) => 'a'];
        $small = $this->capability([$provider], limits: new McpResourceLimits(32, 64, 1));
        $this->expectException(DomainException::class);
        $small->handle($this->decode('resources/list'));
    }

    public function test_that_lookup_cannot_escape_the_serving_descriptor_budget(): void
    {
        $provider = $this->mock(McpReadableResourceProvider::class);
        $provider->shouldReceive('find')->andReturn(McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => str_repeat('n', 100)]));
        $provider->shouldNotReceive('open');
        $this->expectException(DomainException::class);
        $this->capability([$provider], limits: new McpResourceLimits(32, 64, 1))->handle($this->request('test:/a'));
    }

    public function test_that_central_metadata_still_enforces_the_final_read_result_budget(): void
    {
        $provider = new ReadProvider(['test:/a' => 'a']);
        $resources = $this->capability([$provider], readLimits: new McpResourceReadLimits(1, 400));
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo(str_repeat('s', 500), '1'), [$resources]));
        self::assertSame(-32603, $responder->respond($this->wire('resources/read', ['uri' => 'test:/a']))->errorCode());
    }

    public function test_that_encoded_read_results_fit_exactly_or_fail_after_central_metadata_is_added(): void
    {
        foreach ([str_repeat("\0", 8), str_repeat("\xff", 8)] as $bytes) {
            $provider = $this->mock(McpReadableResourceProvider::class);
            $info = McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => 'A', 'size' => 8]);
            $provider->shouldReceive('find')->andReturn($info);
            $provider->shouldReceive('open')->andReturnUsing(fn() => $bytes[0] === "\0" ? McpResourceContent::text($info, Utils::streamFor($bytes)) : McpResourceContent::binary($info, Utils::streamFor($bytes)));
            $resources = $this->capability([$provider], readLimits: new McpResourceReadLimits(8, 400));
            $wire = $this->wire('resources/read', ['uri' => 'test:/a']);
            $base = (new McpResponder(new McpCapabilityRegistry(new McpServerInfo('s', '1'), [$resources])))->respond($wire)->toArray()['result'];
            $padding = 400 - strlen(json_encode($base, JSON_THROW_ON_ERROR));
            $exact = (new McpResponder(new McpCapabilityRegistry(new McpServerInfo(str_repeat('s', $padding + 1), '1'), [$resources])))->respond($wire)->toArray()['result'];
            self::assertSame(400, strlen(json_encode($exact, JSON_THROW_ON_ERROR)));
            $item = $exact['contents'][0];
            self::assertSame($bytes, $item['text'] ?? base64_decode($item['blob'], true));
            $overflow = new McpResponder(new McpCapabilityRegistry(new McpServerInfo(str_repeat('s', $padding + 2), '1'), [$resources]));
            self::assertSame(-32603, $overflow->respond($wire)->errorCode());
        }
    }

    public function test_that_provider_and_policy_failures_including_protocol_injection_are_internal(): void
    {
        foreach (['find', 'open', 'availability'] as $stage) {
            foreach ([new RuntimeException('secret provider detail'), new McpProtocolException(McpProtocolError::unsupportedProtocolVersion(['secret'], 'secret'), 1)] as $error) {
                $info = McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => 'A']);
                $provider = $this->mock(McpReadableResourceProvider::class);
                $availability = $this->mock(McpResourceAvailability::class);
                if ($stage === 'find') { $provider->shouldReceive('find')->andThrow($error); $provider->shouldNotReceive('open'); }
                else {
                    $provider->shouldReceive('find')->andReturn($info);
                    if ($stage === 'availability') { $availability->shouldReceive('isAvailable')->andThrow($error); $provider->shouldNotReceive('open'); }
                    else { $availability->shouldReceive('isAvailable')->andReturn(true); $provider->shouldReceive('open')->andThrow($error); }
                }
                $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Reads', '1'), [$this->capability([$provider], $availability)]));
                self::assertSame(['jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32603, 'message' => 'Internal error.']], $responder->respond($this->wire('resources/read', ['uri' => 'test:/a']))->toArray());
            }
        }
    }

    private function capability(array $providers, ?McpResourceAvailability $availability = null, ?McpResourceLimits $limits = null, ?McpResourceReadLimits $readLimits = null): McpResourceDiscovery
    {
        return new McpResourceDiscovery($providers, $availability ?? new ReadAvailability(), str_repeat('k', 32), 'scope', $limits ?? new McpResourceLimits(pageSize: 1), readLimits: $readLimits ?? new McpResourceReadLimits());
    }

    private function request(string $uri): McpRequest { return $this->decode('resources/read', ['uri' => $uri]); }
    private function decode(string $method, array $parameters = []): McpRequest { return (new McpRequestDecoder())->decode($this->wire($method, $parameters)); }
    private function wire(string $method, array $parameters): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => [...$parameters, '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) [],
        ]]], JSON_THROW_ON_ERROR);
    }
}

final class ReadAvailability implements McpResourceAvailability
{
    public bool $allowed = true;
    public function isAvailable(McpResourceInfo $resource): bool { return $this->allowed; }
}

final class ReadProvider implements McpReadableResourceProvider
{
    public array $opened = [];
    public array $lookedUp = [];
    public int $enumerations = 0;
    public function __construct(public array $entries, private bool $listed = true) {}
    public function resources(): iterable {
        ++$this->enumerations;
        if ($this->listed) { $entries = $this->entries; ksort($entries, SORT_STRING); foreach ($entries as $uri => $bytes) { yield $this->info($uri, $bytes); } }
    }
    public function find(string $uri): ?McpResourceInfo {
        $this->lookedUp[] = $uri;
        return array_key_exists($uri, $this->entries) ? $this->info($uri, $this->entries[$uri]) : null;
    }
    public function open(McpResourceInfo $resource): McpResourceContent {
        $this->opened[] = $resource->uri();
        return McpResourceContent::text($resource, Utils::streamFor($this->entries[$resource->uri()]));
    }
    private function info(string $uri, string $bytes): McpResourceInfo {
        return McpResourceInfo::fromArray(['uri' => $uri, 'name' => $uri, 'mimeType' => 'text/plain', 'size' => strlen($bytes)]);
    }
}
