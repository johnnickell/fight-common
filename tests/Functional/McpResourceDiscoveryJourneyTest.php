<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceProvider;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\App;
use Throwable;

#[CoversNothing]
final class McpResourceDiscoveryJourneyTest extends TestCase
{
    public function test_that_resources_use_the_existing_authenticated_guarded_endpoint_and_current_visibility(): void
    {
        $provider = new class implements McpResourceProvider {
            public int $enumerations = 0;
            public int $contentReads = 0;
            public bool $fail = false;
            public function resources(): iterable {
                ++$this->enumerations;
                yield McpResourceInfo::fromArray(['uri' => 'context:/a', 'name' => '', 'mimeType' => 'text/plain', 'size' => 0]);
                if ($this->fail) { throw new RuntimeException('private catalog credential'); }
                yield McpResourceInfo::fromArray(['uri' => 'context:/b', 'name' => 'B']);
                yield McpResourceInfo::fromArray(['uri' => 'context:/secret', 'name' => 'concealed name']);
            }
            public function read(string $uri): string { ++$this->contentReads; throw new RuntimeException('Discovery must not read content.'); }
        };
        $availability = new class implements McpResourceAvailability {
            public bool $allowed = true;
            public bool $fail = false;
            public function isAvailable(McpResourceInfo $resource): bool {
                if ($this->fail) { throw new RuntimeException('private policy credential'); }
                return $this->allowed && $resource->uri() !== 'context:/secret';
            }
        };
        $guard = new class implements McpInvocationGuard {
            public bool $allowed = true;
            public array $calls = [];
            public function allows(string $method, ?string $name): bool { $this->calls[] = [$method, $name]; return $this->allowed; }
        };
        $diagnostics = new class implements McpDiagnostics {
            public array $events = [];
            public function record(Throwable $failure): void { $this->events[] = 'resource_failure'; }
        };
        $factory = new HttpFactory();
        $endpoint = new McpRequestHandler(
            new McpCapabilityRegistry(new McpServerInfo('Resource journey', '1'), [new McpResourceDiscovery(
                [$provider], $availability, 'fixture-only-resource-cursor-key-00117', 'journey', new McpResourceLimits(pageSize: 1),
            )]),
            new ExactMcpOriginPolicy(['https://client.test']), $guard,
            new McpResponseFactory($factory, $factory, $diagnostics),
        );
        $app = new App($factory);
        $app->post('/consumer/resources', fn(ServerRequestInterface $request) => $endpoint->handle($request));
        $app->add(function(ServerRequestInterface $request, RequestHandlerInterface $next) use ($factory) {
            return $request->getHeaderLine('Authorization') === 'Bearer fixture-only' ? $next->handle($request) : $factory->createResponse(401);
        });
        $request = $this->request('resources/list');
        foreach ([
            [$request->withoutHeader('Authorization'), 401],
            [$request->withHeader('Origin', 'https://evil.test'), 403],
            [$request->withoutHeader('Mcp-Method'), 400],
            [$request->withHeader('Mcp-Method', 'resources/templates/list'), 400],
            [$request->withoutHeader('MCP-Protocol-Version'), 400],
            [$request->withHeader('MCP-Protocol-Version', '2025-11-25'), 400],
            [$request->withAddedHeader('Mcp-Method', 'resources/list'), 400],
            [$request->withHeader('Accept', 'application/json'), 406],
        ] as [$rejected, $status]) {
            self::assertSame($status, $app->handle($rejected)->getStatusCode());
            self::assertSame(0, $provider->enumerations);
            self::assertSame([], $guard->calls);
        }
        $guard->allowed = false;
        self::assertSame(429, $app->handle($request)->getStatusCode());
        self::assertSame(0, $provider->enumerations);
        $guard->allowed = true;
        $response = $app->handle($request->withHeader('Origin', 'https://client.test'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $first = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['result'];
        self::assertSame([['uri' => 'context:/a', 'name' => '', 'mimeType' => 'text/plain', 'size' => 0]], $first['resources']);
        self::assertSame('complete', $first['resultType']);
        self::assertSame(0, $first['ttlMs']);
        self::assertSame('private', $first['cacheScope']);
        self::assertSame(['name' => 'Resource journey', 'version' => '1'], $first['_meta']['io.modelcontextprotocol/serverInfo']);
        self::assertStringNotContainsString('secret', (string) $response->getBody());
        self::assertSame([['resources/list', null], ['resources/list', null]], $guard->calls);

        $second = json_decode((string) $app->handle($this->request('resources/list', ['cursor' => $first['nextCursor']]))->getBody(), true)['result'];
        self::assertSame(['context:/b'], array_column($second['resources'], 'uri'));
        self::assertArrayNotHasKey('nextCursor', $second);
        self::assertSame(2, $provider->enumerations);
        $templates = json_decode((string) $app->handle($this->request('resources/templates/list', ['cursor' => '']))->getBody(), true)['result'];
        self::assertSame([], $templates['resourceTemplates']);
        self::assertArrayNotHasKey('nextCursor', $templates);
        self::assertSame(2, $provider->enumerations);
        $server = json_decode((string) $app->handle($this->request('server/discover'))->getBody(), true)['result'];
        self::assertSame(['resources' => ['subscribe' => false, 'listChanged' => false]], $server['capabilities']);

        $availability->allowed = false;
        $revoked = $app->handle($this->request('resources/list', ['cursor' => $first['nextCursor']]));
        self::assertSame(-32602, json_decode((string) $revoked->getBody(), true)['error']['code']);
        self::assertStringNotContainsString('context:/', (string) $revoked->getBody());
        $empty = json_decode((string) $app->handle($request)->getBody(), true)['result'];
        self::assertSame([], $empty['resources']);
        self::assertArrayNotHasKey('nextCursor', $empty);
        self::assertSame([], $diagnostics->events);

        $availability->allowed = true;
        foreach (['provider', 'availability'] as $failing) {
            $provider->fail = $failing === 'provider';
            $availability->fail = $failing === 'availability';
            $failure = $app->handle($request);
            self::assertSame(500, $failure->getStatusCode());
            self::assertSame(['jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32603, 'message' => 'Internal error.']], json_decode((string) $failure->getBody(), true));
        }
        self::assertSame(['resource_failure', 'resource_failure'], $diagnostics->events);
        self::assertSame(0, $provider->contentReads);
    }

    private function request(string $method, array $parameters = []): ServerRequestInterface
    {
        return new ServerRequest('POST', 'https://server.test/consumer/resources', [
            'Authorization' => 'Bearer fixture-only', 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method,
        ], json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => [...$parameters, '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) [],
        ]]], JSON_THROW_ON_ERROR));
    }
}
