<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Resource;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceProvider;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(McpResourceDiscovery::class)]
final class McpResourceDiscoveryTest extends UnitTestCase
{
    private const string KEY = 'resource-test-only-secret-00117-32-bytes';

    public function test_that_discovery_merges_streams_and_filters_before_paging_without_opening_content(): void
    {
        $availability = new ResourceAvailability();
        $providers = [$this->provider(['test:/b', 'test:/z']), $this->provider(['test:/a', 'test:/hidden'])];
        $discovery = $this->discovery($providers, $availability);
        $first = $discovery->handle($this->request())->toArray();
        self::assertSame(['test:/a'], array_column($first['resources'], 'uri'));
        self::assertSame(['test:/a', 'test:/b', 'test:/hidden', 'test:/z'], $availability->seen);
        self::assertSame('complete', $first['resultType']);
        self::assertSame(0, $first['ttlMs']);
        self::assertSame('private', $first['cacheScope']);
        self::assertSame(48, strlen($first['nextCursor']));
        self::assertStringNotContainsString('hidden', json_encode($first, JSON_THROW_ON_ERROR));
        self::assertSame($first, $discovery->handle($this->request(['cursor' => '']))->toArray());

        // Reordering providers and recomposing for another request does not alter continuation identity.
        $fresh = $this->discovery(array_reverse($providers), new ResourceAvailability());
        $second = $fresh->handle($this->request(['cursor' => $first['nextCursor']]))->toArray();
        self::assertSame(['test:/b'], array_column($second['resources'], 'uri'));
        $last = $fresh->handle($this->request(['cursor' => $second['nextCursor']]))->toArray();
        self::assertSame(['test:/z'], array_column($last['resources'], 'uri'));
        self::assertArrayNotHasKey('nextCursor', $last);
    }

    public function test_that_registration_does_not_retain_caller_array_references(): void
    {
        $provider = $this->provider(['test:/registered']);
        $providers = [&$provider];
        $discovery = $this->discovery($providers);
        $provider = $this->provider(['test:/replacement']);
        self::assertSame(['test:/registered'], array_column($discovery->handle($this->request())->toArray()['resources'], 'uri'));
    }

    public function test_that_hidden_catalog_changes_do_not_change_visible_pages_or_cursor_bytes(): void
    {
        $availability = new ResourceAvailability();
        $first = $this->discovery([$this->provider(['test:/a', 'test:/hidden', 'test:/z'])], $availability)->handle($this->request())->toArray();
        $withoutHidden = $this->discovery([$this->provider(['test:/a', 'test:/z'])], $availability);
        self::assertSame($first, $withoutHidden->handle($this->request())->toArray());
        self::assertSame(['test:/z'], array_column($withoutHidden->handle($this->request(['cursor' => $first['nextCursor']]))->toArray()['resources'], 'uri'));
    }

    public function test_that_empty_catalogs_and_template_discovery_are_complete_without_false_continuations(): void
    {
        $discovery = $this->discovery([]);
        self::assertSame(['resultType' => 'complete', 'resources' => [], 'ttlMs' => 0, 'cacheScope' => 'private'], $discovery->handle($this->request())->toArray());
        $provider = $this->mock(McpResourceProvider::class);
        $provider->shouldNotReceive('resources');
        $templates = $this->discovery([$provider]);
        self::assertSame(['resultType' => 'complete', 'resourceTemplates' => [], 'ttlMs' => 0, 'cacheScope' => 'private'],
            $templates->handle($this->request(['cursor' => ''], 'resources/templates/list'))->toArray());
    }

    public function test_that_discovery_advertises_no_read_or_subscription_handler(): void
    {
        $discovery = $this->discovery([]);
        self::assertSame(['resources/list', 'resources/templates/list'], $discovery->methods());
        self::assertSame([], $discovery->mirrorDeclarations());
        $registry = new McpCapabilityRegistry(new McpServerInfo('Resources', '1'), [$discovery]);
        self::assertSame(['resources' => ['subscribe' => false, 'listChanged' => false]], $registry->advertisedCapabilities());
        self::assertNull($registry->capabilityFor('resources/read'));
        self::assertSame(-32601, (new McpResponder($registry))->dispatch($this->request(['uri' => 'test:/a'], 'resources/read'))->errorCode());
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(new McpServerInfo('Resources', '1'), [$discovery, $discovery]);
    }

    public function test_that_contradictory_resource_advertisement_fails_instead_of_overwriting(): void
    {
        $other = $this->mock(McpCapability::class);
        $other->shouldReceive('methods')->andReturn(['resources/read']);
        $other->shouldReceive('capabilities')->andReturn(['resources' => ['subscribe' => true]]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('contradictory');
        new McpCapabilityRegistry(new McpServerInfo('Resources', '1'), [$this->discovery([]), $other]);
    }

    public function test_that_cache_overrides_do_not_cache_availability_or_change_server_discovery_policy(): void
    {
        $availability = new ResourceAvailability();
        $discovery = new McpResourceDiscovery([$this->provider(['test:/a'])], $availability, self::KEY, 'catalog', ttlMs: 1000, cacheScope: 'public');
        $first = $discovery->handle($this->request())->toArray();
        self::assertSame(1000, $first['ttlMs']);
        self::assertSame('public', $first['cacheScope']);
        $availability->hidden = 'test:/a';
        self::assertSame([], $discovery->handle($this->request())->toArray()['resources']);
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Resources', '1'), [$discovery]));
        $server = $responder->dispatch($this->request(method: 'server/discover'))->toArray()['result'];
        self::assertSame(0, $server['ttlMs']);
        self::assertSame('private', $server['cacheScope']);
    }

    #[DataProvider('invalidConfiguration')]
    public function test_that_invalid_configuration_fails_before_enumeration(array $arguments): void
    {
        $this->expectException(DomainException::class);
        new McpResourceDiscovery(...array_replace(['providers' => [], 'availability' => new ResourceAvailability(), 'cursorKey' => self::KEY, 'cursorScope' => 'catalog'], $arguments));
    }

    public static function invalidConfiguration(): iterable
    {
        yield [['cursorKey' => 'short']];
        yield [['cursorKey' => str_repeat('x', 4097)]];
        yield [['cursorScope' => '']];
        yield [['cursorScope' => str_repeat('s', 129)]];
        yield [['ttlMs' => -1]];
        yield [['ttlMs' => 9007199254740992]];
        yield [['cacheScope' => 'shared']];
        yield [['providers' => [false]]];
        yield [['providers' => ['name' => false]]];
    }

    public function test_that_repeated_provider_instances_and_provider_count_overflow_fail_composition(): void
    {
        $provider = $this->provider([]);
        foreach ([[$provider, $provider], [$provider, $this->provider([])]] as $providers) {
            try {
                new McpResourceDiscovery($providers, new ResourceAvailability(), self::KEY, 'catalog', new McpResourceLimits(maxProviders: count($providers) === 2 && $providers[0] !== $providers[1] ? 1 : 2));
                self::fail('Ambiguous or excessive providers accepted.');
            } catch (DomainException) { self::assertTrue(true); }
        }
    }

    #[DataProvider('invalidParameters')]
    public function test_that_invalid_requests_never_enumerate(array $parameters, string $method = 'resources/list'): void
    {
        $provider = $this->mock(McpResourceProvider::class);
        $provider->shouldNotReceive('resources');
        $this->expectException(McpProtocolException::class);
        $this->discovery([$provider])->handle($this->request($parameters, $method));
    }

    public static function invalidParameters(): iterable
    {
        yield [['unknown' => true]];
        foreach ([null, 1, [], 'x', str_repeat('!', 48), str_repeat('a', 49)] as $cursor) { yield [['cursor' => $cursor]]; }
        yield [[], 'resources/read'];
        yield [['cursor' => str_repeat('a', 48)], 'resources/templates/list'];
        yield [['cursor' => strtr(base64_encode(pack('N', 0).str_repeat('x', 32)), '+/', '-_')]];
    }

    public function test_that_tampered_foreign_and_stale_cursors_fail_without_disclosing_descriptors(): void
    {
        $availability = new ResourceAvailability();
        $provider = $this->provider(['test:/a', 'test:/b', 'test:/c']);
        $discovery = $this->discovery([$provider], $availability);
        $cursor = $discovery->handle($this->request())->toArray()['nextCursor'];
        $raw = base64_decode(strtr($cursor, '-_', '+/'));
        $variants = [
            [$discovery, strtr(base64_encode(pack('N', 9).substr($raw, 4)), '+/', '-_')],
            [$discovery, strtr(base64_encode(pack('N', 1).str_repeat('x', 32)), '+/', '-_')],
            [new McpResourceDiscovery([$provider], $availability, str_repeat('z', 32), 'catalog', new McpResourceLimits(pageSize: 1)), $cursor],
            [new McpResourceDiscovery([$provider], $availability, self::KEY, 'other', new McpResourceLimits(pageSize: 1)), $cursor],
            [new McpResourceDiscovery([$provider], $availability, self::KEY, 'catalog', new McpResourceLimits(pageSize: 2)), $cursor],
            [$this->discovery([$this->provider(['test:/b', 'test:/c'])]), $cursor],
        ];
        foreach ($variants as [$capability, $value]) {
            try { $capability->handle($this->request(['cursor' => $value])); self::fail('Invalid continuation accepted.'); }
            catch (McpProtocolException $error) { self::assertSame(-32602, $error->protocolError()->toArray()['code']); self::assertSame(7, $error->requestId()); }
        }
        $availability->hidden = 'test:/a';
        $this->expectException(McpProtocolException::class);
        $discovery->handle($this->request(['cursor' => $cursor]));
    }

    public function test_that_changed_visible_metadata_invalidates_continuation(): void
    {
        $cursor = $this->discovery([$this->provider(['test:/a', 'test:/b'])])->handle($this->request())->toArray()['nextCursor'];
        $changed = $this->provider([McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => 'changed']), 'test:/b']);
        $this->expectException(McpProtocolException::class);
        $this->discovery([$changed])->handle($this->request(['cursor' => $cursor]));
    }

    public function test_that_duplicate_hidden_ownership_unsorted_and_malformed_output_fail_even_beyond_the_page(): void
    {
        foreach ([
            [$this->provider(['test:/a', 'test:/hidden']), $this->provider(['test:/hidden'])],
            [$this->provider(['test:/b', 'test:/a'])],
            [$this->provider(['test:/a', 'test:/a'])],
            [$this->provider(['test:/a', 1])],
            [$this->provider(['test:/a', McpResourceInfo::fromArray(['uri' => 'test:/b', 'name' => str_repeat('n', 200)])])],
        ] as $providers) {
            try { $this->discovery($providers, limits: new McpResourceLimits(32, 128, 1))->handle($this->request()); self::fail('Invalid provider output accepted.'); }
            catch (DomainException) { self::assertTrue(true); }
        }
    }

    public function test_that_scan_limits_count_hidden_metadata_and_stop_lazy_providers(): void
    {
        $provider = new class implements McpResourceProvider {
            public int $yielded = 0;
            public function resources(): iterable {
                for ($i = 0; $i < 1000000; ++$i) { ++$this->yielded; yield McpResourceInfo::fromArray(['uri' => 'test:/'.sprintf('%07d', $i), 'name' => 'row']); }
            }
        };
        $availability = $this->mock(McpResourceAvailability::class);
        $availability->shouldReceive('isAvailable')->twice()->andReturn(false);
        try { $this->discovery([$provider], $availability, new McpResourceLimits(pageSize: 1, maxDescriptors: 2))->handle($this->request()); self::fail('Unbounded scan accepted.'); }
        catch (DomainException) { self::assertSame(3, $provider->yielded); }
        $exact = $this->discovery([$this->provider(['test:/a', 'test:/b'])], limits: new McpResourceLimits(pageSize: 2, maxDescriptors: 2));
        self::assertCount(2, $exact->handle($this->request())->toArray()['resources']);
    }

    public function test_that_result_bounds_reject_overflow_including_the_complete_result_wrapper(): void
    {
        $metadata = fn(string $uri, int $length) => McpResourceInfo::fromArray(['uri' => $uri, 'name' => str_repeat('a', $length)]);
        // First case exceeds descriptor accumulation; second fits descriptors but exceeds the complete wrapper.
        foreach ([150, 115] as $length) {
            $providers = [$this->provider([$metadata('test:/a', $length), $metadata('test:/b', $length), $metadata('test:/c', $length)])];
            try { $this->discovery($providers, limits: new McpResourceLimits(32, 200, 3, maxResultBytes: 456))->handle($this->request()); self::fail('Oversized page accepted.'); }
            catch (DomainException $error) { self::assertStringContainsString('encoded result budget', $error->getMessage()); }
        }
        $data = $metadata('test:/a', 115);
        $result = $this->discovery([$this->provider([$data])])->handle($this->request())->toArray();
        self::assertSame($data->toArray(), $result['resources'][0]);
    }

    public function test_that_central_response_metadata_cannot_escape_the_discovery_result_budget(): void
    {
        $discovery = $this->discovery([], limits: new McpResourceLimits(32, 64, 1, maxResultBytes: 320));
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo(str_repeat('s', 500), '1'), [$discovery]));
        foreach (['resources/list', 'resources/templates/list'] as $method) {
            self::assertSame(-32603, $responder->respond($this->wire(method: $method))->errorCode());
        }
    }

    public function test_that_provider_and_availability_failures_are_not_returned_as_partial_success(): void
    {
        $provider = new class implements McpResourceProvider {
            public function resources(): iterable { yield McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => 'a']); throw new RuntimeException('secret storage detail'); }
        };
        $availability = $this->mock(McpResourceAvailability::class);
        $availability->shouldReceive('isAvailable')->andThrow(new RuntimeException('secret policy detail'));
        foreach ([$this->discovery([$provider]), $this->discovery([$this->provider(['test:/a'])], $availability)] as $discovery) {
            $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Resources', '1'), [$discovery]));
            $response = $responder->respond($this->wire());
            self::assertSame(['jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32603, 'message' => 'Internal error.']], $response->toArray());
        }
    }

    public function test_that_collaborators_cannot_publish_their_own_protocol_errors(): void
    {
        $failure = new McpProtocolException(McpProtocolError::unsupportedProtocolVersion(['private-revision'], 'secret'), 7);
        $provider = $this->mock(McpResourceProvider::class);
        $provider->shouldReceive('resources')->andThrow($failure);
        $availability = $this->mock(McpResourceAvailability::class);
        $availability->shouldReceive('isAvailable')->andThrow($failure);
        foreach ([$this->discovery([$provider]), $this->discovery([$this->provider(['test:/a'])], $availability)] as $discovery) {
            $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Resources', '1'), [$discovery]));
            self::assertSame(['jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32603, 'message' => 'Internal error.']], $responder->respond($this->wire())->toArray());
        }
    }

    private function discovery(array $providers, ?McpResourceAvailability $availability = null, ?McpResourceLimits $limits = null): McpResourceDiscovery
    {
        return new McpResourceDiscovery($providers, $availability ?? new ResourceAvailability(), self::KEY, 'catalog', $limits ?? new McpResourceLimits(pageSize: 1));
    }

    private function provider(array $entries): McpResourceProvider
    {
        return new class ($entries) implements McpResourceProvider {
            public function __construct(private array $entries) {}
            public function resources(): Generator {
                foreach ($this->entries as $entry) { yield is_string($entry) ? McpResourceInfo::fromArray(['uri' => $entry, 'name' => $entry]) : $entry; }
            }
        };
    }

    private function request(array $parameters = [], string $method = 'resources/list'): McpRequest
    {
        return (new McpRequestDecoder())->decode($this->wire($parameters, $method));
    }

    private function wire(array $parameters = [], string $method = 'resources/list'): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => [...$parameters, '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) [],
        ]]], JSON_THROW_ON_ERROR);
    }
}

final class ResourceAvailability implements McpResourceAvailability
{
    public array $seen = [];
    public string $hidden = 'test:/hidden';
    public function isAvailable(McpResourceInfo $resource): bool
    {
        $this->seen[] = $resource->uri();
        return $resource->uri() !== $this->hidden;
    }
}
