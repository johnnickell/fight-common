<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\Fixture\Mcp\DiscoveryTool;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;

#[CoversClass(McpToolDiscovery::class)]
#[CoversClass(McpToolRegistry::class)]
final class McpToolDiscoveryTest extends UnitTestCase
{
    private const string KEY = 'test-only-cursor-key-not-for-production-00106';

    public function test_that_discovery_filters_before_sorting_and_paginates_only_available_definitions(): void
    {
        $availability = new DiscoveryAvailability();
        $discovery = $this->discovery($availability);
        $first = $discovery->handle($this->request())->toArray();

        self::assertSame(['zulu.read', 'hidden.internal', 'alpha.find'], $availability->seen);
        self::assertSame(['alpha.find'], array_column($first['tools'], 'name'));
        self::assertSame(0, $first['ttlMs']);
        self::assertSame('private', $first['cacheScope']);
        self::assertSame('complete', $first['resultType']);
        self::assertArrayHasKey('nextCursor', $first);
        self::assertStringNotContainsString('hidden', json_encode($first, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('secret policy reason', json_encode($first, JSON_THROW_ON_ERROR));
        self::assertSame(48, strlen($first['nextCursor']));

        // A separately composed request/process with the same key and catalog can continue.
        $second = $this->discovery(new DiscoveryAvailability())->handle(
            $this->request(['cursor' => $first['nextCursor']]),
        )->toArray();
        self::assertSame(['zulu.read'], array_column($second['tools'], 'name'));
        self::assertArrayNotHasKey('nextCursor', $second);
    }

    public function test_that_hidden_registry_changes_do_not_influence_cursors_or_available_results(): void
    {
        $first = $this->discovery(new DiscoveryAvailability())->handle($this->request())->toArray();
        $tools = $this->tools();
        $withoutHidden = new McpToolDiscovery(
            new McpToolRegistry([$tools[2], $tools[0]]), new DiscoveryAvailability(), self::KEY, 1,
        );
        self::assertEquals($first, $withoutHidden->handle($this->request())->toArray());
        self::assertSame(['zulu.read'], array_column($withoutHidden->handle(
            $this->request(['cursor' => $first['nextCursor']]),
        )->toArray()['tools'], 'name'));
    }

    public function test_that_empty_and_small_catalogs_omit_the_continuation(): void
    {
        $empty = new McpToolDiscovery(new McpToolRegistry([]), new DiscoveryAvailability(), self::KEY);
        self::assertSame([
            'resultType' => 'complete', 'tools' => [], 'ttlMs' => 0, 'cacheScope' => 'private',
        ], $empty->handle($this->request())->toArray());
        $small = new McpToolDiscovery(new McpToolRegistry($this->tools()), new DiscoveryAvailability(), self::KEY);
        $result = $small->handle($this->request())->toArray();
        self::assertSame(['alpha.find', 'zulu.read'], array_column($result['tools'], 'name'));
        self::assertArrayNotHasKey('nextCursor', $result);
    }

    public function test_that_cache_overrides_are_explicit_and_do_not_change_server_discovery_defaults(): void
    {
        $capability = new McpToolDiscovery(
            new McpToolRegistry([]), new DiscoveryAvailability(), self::KEY, ttlMs: 5000, cacheScope: 'public',
        );
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Consumer', '1.0'), [$capability]));
        $list = $responder->respond($this->wire('tools/list'))->toArray()['result'];
        self::assertSame(5000, $list['ttlMs']);
        self::assertSame('public', $list['cacheScope']);
        $server = $responder->respond($this->wire('server/discover'))->toArray()['result'];
        self::assertSame(0, $server['ttlMs']);
        self::assertSame('private', $server['cacheScope']);
    }

    public function test_that_capability_composition_advertises_only_discovery_and_cannot_dispatch_tools(): void
    {
        $capability = $this->discovery(new DiscoveryAvailability());
        $registry = new McpCapabilityRegistry(new McpServerInfo('Consumer', '1.0'), [$capability]);
        self::assertSame(['tools/list'], $capability->methods());
        self::assertSame(['tools' => ['listChanged' => false]], $registry->advertisedCapabilities());
        self::assertSame([], $capability->mirrorDeclarations());
        self::assertSame($capability, $registry->capabilityFor('tools/list'));
        self::assertNull($registry->capabilityFor('tools/call'));
        $responder = new McpResponder($registry);
        self::assertSame(-32601, $responder->respond($this->wire('tools/call', ['name' => 'alpha.find']))->errorCode());
        $result = $responder->respond($this->wire('tools/list'))->toArray()['result'];
        self::assertEquals([
            'name' => 'alpha.find',
            'description' => 'Find public data',
            'inputSchema' => (object) [
                'type' => 'object', 'properties' => (object) ['id' => (object) ['type' => 'string']], 'required' => ['id'],
            ],
            'outputSchema' => (object) ['type' => 'object', 'properties' => (object) ['id' => (object) ['type' => 'string']]],
        ], $result['tools'][0]);
        self::assertSame(['name' => 'Consumer', 'version' => '1.0'], $result['_meta']['io.modelcontextprotocol/serverInfo']);
    }

    public function test_that_contradictory_tool_capability_metadata_fails_composition(): void
    {
        $contradictory = $this->mock(McpCapability::class);
        $contradictory->shouldReceive('methods')->andReturn(['tools/call']);
        $contradictory->shouldReceive('capabilities')->andReturn(['tools' => ['listChanged' => true]]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('contradictory');
        new McpCapabilityRegistry(new McpServerInfo('Consumer', '1.0'), [
            $this->discovery(new DiscoveryAvailability()), $contradictory,
        ]);
    }

    #[DataProvider('invalidConfiguration')]
    public function test_that_unsafe_pagination_and_cache_configuration_fails(array $arguments): void
    {
        $this->expectException(DomainException::class);
        new McpToolDiscovery(new McpToolRegistry([]), new DiscoveryAvailability(), ...$arguments);
    }

    public static function invalidConfiguration(): iterable
    {
        yield [['cursorKey' => 'short']];
        yield [['cursorKey' => self::KEY, 'pageSize' => 0]];
        yield [['cursorKey' => self::KEY, 'pageSize' => 1001]];
        yield [['cursorKey' => self::KEY, 'ttlMs' => -1]];
        yield [['cursorKey' => self::KEY, 'ttlMs' => 9007199254740992]];
        yield [['cursorKey' => self::KEY, 'cacheScope' => 'shared']];
    }

    #[DataProvider('invalidParameters')]
    public function test_that_invalid_outer_parameters_reject_before_availability(array $parameters): void
    {
        $availability = new DiscoveryAvailability();
        try {
            $this->discovery($availability)->handle($this->request($parameters));
            self::fail('Invalid parameters were accepted.');
        } catch (McpProtocolException $exception) {
            self::assertSame(-32602, $exception->protocolError()->toArray()['code']);
            self::assertSame(7, $exception->requestId());
            self::assertSame([], $availability->seen);
        }
    }

    public static function invalidParameters(): iterable
    {
        yield [['unexpected' => true]];
        yield [['cursor' => null]];
        yield [['cursor' => 1]];
        yield [['cursor' => []]];
        yield [['cursor' => '']];
        yield [['cursor' => str_repeat('!', 48)]];
        yield [['cursor' => str_repeat('a', 49)]];
    }

    public function test_that_direct_capability_calls_cannot_bypass_method_validation(): void
    {
        $this->expectException(McpProtocolException::class);
        $this->discovery(new DiscoveryAvailability())->handle($this->request(method: 'tools/call'));
    }

    public function test_that_manipulated_or_foreign_cursors_fail_without_returning_any_definitions(): void
    {
        $discovery = $this->discovery(new DiscoveryAvailability());
        $cursor = $discovery->handle($this->request())->toArray()['nextCursor'];
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        $invalid = [
            strtr(base64_encode(pack('N', 0).substr($raw, 4)), '+/', '-_'),
            strtr(base64_encode(pack('N', 2).substr($raw, 4)), '+/', '-_'),
            strtr(base64_encode(pack('N', 1).str_repeat('x', 32)), '+/', '-_'),
        ];
        foreach ($invalid as $value) {
            $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Consumer', '1.0'), [$discovery]));
            self::assertSame([
                'jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32602, 'message' => 'Invalid params.'],
            ], $responder->respond($this->wire('tools/list', ['cursor' => $value]))->toArray());
        }
        $otherKey = new McpToolDiscovery(new McpToolRegistry($this->tools()), new DiscoveryAvailability(), str_repeat('k', 32), 1);
        $this->expectException(McpProtocolException::class);
        $otherKey->handle($this->request(['cursor' => $cursor]));
    }

    public function test_that_current_availability_changes_invalidate_continuation_instead_of_leaking_or_skipping(): void
    {
        $availability = new DiscoveryAvailability();
        $discovery = $this->discovery($availability);
        $cursor = $discovery->handle($this->request())->toArray()['nextCursor'];
        $availability->hidden = 'alpha.find';
        $this->expectException(McpProtocolException::class);
        $discovery->handle($this->request(['cursor' => $cursor]));
    }

    public function test_that_a_cursor_cannot_be_reused_under_a_different_page_size(): void
    {
        $availability = new DiscoveryAvailability();
        $availability->hidden = '';
        $cursor = $this->discovery($availability)->handle($this->request())->toArray()['nextCursor'];
        $different = new McpToolDiscovery(new McpToolRegistry($this->tools()), $availability, self::KEY, 2);
        $this->expectException(McpProtocolException::class);
        $different->handle($this->request(['cursor' => $cursor]));
    }

    public function test_that_availability_failure_propagates_to_the_existing_diagnostic_boundary(): void
    {
        $availability = $this->mock(McpToolAvailability::class);
        $availability->shouldReceive('isAvailable')->andThrow(new RuntimeException('private policy details'));
        $capability = $this->discovery($availability);
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Consumer', '1.0'), [$capability]));
        self::assertSame([
            'jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32603, 'message' => 'Internal error.'],
        ], $responder->respond($this->wire('tools/list'))->toArray());
        $this->expectException(RuntimeException::class);
        $responder->dispatch($this->request());
    }

    private function discovery(McpToolAvailability $availability): McpToolDiscovery
    {
        return new McpToolDiscovery(new McpToolRegistry($this->tools()), $availability, self::KEY, 1);
    }

    private function tools(): array
    {
        return [
            new class implements McpTool {
                #[McpToolInfo('zulu.read', 'Read public data', ['type' => 'object'], ['type' => 'string'])]
                public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
                {
                    throw new LogicException('Discovery must never invoke a Tool.');
                }
            },
            new class implements McpTool {
                #[McpToolInfo('hidden.internal', 'Concealed definition', ['type' => 'object'], [])]
                public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
                {
                    throw new LogicException('Discovery must never invoke a Tool.');
                }
            },
            new DiscoveryTool(),
        ];
    }

    private function request(array $parameters = [], string $method = 'tools/list'): McpRequest
    {
        return (new McpRequestDecoder())->decode($this->wire($method, $parameters));
    }

    private function wire(string $method, array $parameters = []): string
    {
        return json_encode([
            'jsonrpc' => '2.0', 'id' => 7, 'method' => $method,
            'params' => [...$parameters, '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => new stdClass(),
            ]],
        ], JSON_THROW_ON_ERROR);
    }
}

final class DiscoveryAvailability implements McpToolAvailability
{
    public array $seen = [];
    public string $hidden = 'hidden.internal';

    public function isAvailable(McpToolInfo $tool): bool
    {
        $this->seen[] = $tool->name();
        return $tool->name() !== $this->hidden;
    }
}
