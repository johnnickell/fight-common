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
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Test\Common\Fixture\Mcp\ReadableResources;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Throwable;

#[CoversNothing]
final class McpResourceReadJourneyTest extends TestCase
{
    public function test_that_authenticated_guarded_list_to_read_and_direct_reads_preserve_bytes_and_authority(): void
    {
        $text = "# Guide 雪\r\ncafé\r\n";
        $documents = new ReadableResources([
            'file:///docs/a' => ['bytes' => $text],
            'file:///docs/hidden' => ['bytes' => 'secret'],
            'file:///docs/unlisted' => ['bytes' => '{{ template }} <?php never_execute();', 'listed' => false],
        ]);
        $assets = new ReadableResources([
            'asset:/binary' => ['bytes' => "\0\xff\x80\r\n", 'binary' => true, 'mimeType' => 'application/octet-stream'],
            'asset:/empty' => ['bytes' => '', 'binary' => true],
        ]);
        $availability = new class implements McpResourceAvailability {
            public bool $allowed = true;
            public function isAvailable(McpResourceInfo $resource): bool { return $this->allowed && $resource->uri() !== 'file:///docs/hidden'; }
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
            new McpCapabilityRegistry(new McpServerInfo('Resource read journey', '1'), [new McpResourceDiscovery(
                [$documents, $assets], $availability, str_repeat('fixture-key-', 4), 'journey', new McpResourceLimits(pageSize: 1), readLimits: new McpResourceReadLimits(64, 1024),
            )]),
            new ExactMcpOriginPolicy(['https://client.test']), $guard, new McpResponseFactory($factory, $factory, $diagnostics),
        );
        $app = new App($factory);
        $app->post('/consumer/resources', fn(ServerRequestInterface $request) => $endpoint->handle($request));
        $app->add(function(ServerRequestInterface $request, RequestHandlerInterface $next) use ($factory) {
            return $request->getHeaderLine('Authorization') === 'Bearer fixture-only' ? $next->handle($request) : $factory->createResponse(401);
        });
        $request = $this->request('resources/read', ['uri' => 'file:///docs/a']);
        foreach ([
            [$request->withoutHeader('Authorization'), 401],
            [$request->withHeader('Origin', 'https://evil.test'), 403],
            [$request->withoutHeader('Mcp-Name'), 400],
            [$request->withHeader('Mcp-Name', 'file:///docs/hidden'), 400],
            [$request->withAddedHeader('Mcp-Name', 'file:///docs/a'), 400],
            [$request->withoutHeader('Mcp-Method'), 400],
            [$request->withHeader('Accept', 'application/json'), 406],
        ] as [$rejected, $status]) {
            self::assertSame($status, $app->handle($rejected)->getStatusCode());
            self::assertSame([], $documents->lookedUp);
            self::assertSame([], $documents->opened);
            self::assertSame([], $guard->calls);
        }
        $guard->allowed = false;
        self::assertSame(429, $app->handle($request)->getStatusCode());
        self::assertSame([], $documents->lookedUp);
        $guard->allowed = true;

        // This read precedes all discovery and requests an identity deliberately omitted from listing.
        $unlisted = $this->responseResult($app, 'resources/read', ['uri' => 'file:///docs/unlisted']);
        self::assertSame('{{ template }} <?php never_execute();', $unlisted['contents'][0]['text']);
        self::assertSame(0, $documents->enumerations + $assets->enumerations);
        self::assertSame([], $assets->opened);
        self::assertSame(['resources/read', 'file:///docs/unlisted'], end($guard->calls));

        $uris = [];
        $parameters = [];
        do {
            $page = $this->responseResult($app, 'resources/list', $parameters);
            $uris = [...$uris, ...array_column($page['resources'], 'uri')];
            $parameters = isset($page['nextCursor']) ? ['cursor' => $page['nextCursor']] : [];
        } while (isset($page['nextCursor']));
        self::assertSame(['asset:/binary', 'asset:/empty', 'file:///docs/a'], $uris);
        self::assertSame(['file:///docs/unlisted'], $documents->opened);
        self::assertSame([], $assets->opened);
        $read = $this->responseResult($app, 'resources/read', ['uri' => 'file:///docs/a']);
        self::assertSame([['uri' => 'file:///docs/a', 'mimeType' => 'text/plain', 'text' => $text]], $read['contents']);
        self::assertSame('complete', $read['resultType']);
        self::assertSame(0, $read['ttlMs']);
        self::assertSame('private', $read['cacheScope']);
        self::assertSame(['name' => 'Resource read journey', 'version' => '1'], $read['_meta']['io.modelcontextprotocol/serverInfo']);
        $binary = $this->responseResult($app, 'resources/read', ['uri' => 'asset:/binary']);
        self::assertSame("\0\xff\x80\r\n", base64_decode($binary['contents'][0]['blob'], true));
        self::assertSame([['uri' => 'asset:/empty', 'mimeType' => 'text/plain', 'blob' => '']], $this->responseResult($app, 'resources/read', ['uri' => 'asset:/empty'])['contents']);
        self::assertSame([], $this->responseResult($app, 'resources/templates/list')['resourceTemplates']);
        self::assertSame(['resources' => ['subscribe' => false, 'listChanged' => false]], $this->responseResult($app, 'server/discover')['capabilities']);

        $availability->allowed = false;
        $opened = $documents->opened;
        $denied = (string) $app->handle($request)->getBody();
        self::assertSame(-32602, json_decode($denied, true)['error']['code']);
        foreach (['file:///docs/hidden', 'file:///missing', 'file:///docs/%61', 'file:///docs/../a', 'https://127.0.0.1/private'] as $uri) {
            self::assertSame($denied, (string) $app->handle($this->request('resources/read', ['uri' => $uri]))->getBody());
        }
        self::assertSame($opened, $documents->opened);
        self::assertSame([], $this->responseResult($app, 'resources/list')['resources']);
        self::assertSame([], $diagnostics->events);
        $availability->allowed = true;
        self::assertSame($text, $this->responseResult($app, 'resources/read', ['uri' => 'file:///docs/a'])['contents'][0]['text']);

        $documents->fail = true;
        $this->assertInternal($app, $request);
        $documents->fail = false;
        $documents->entries['file:///docs/a']['bytes'] = "\xff";
        $this->assertInternal($app, $request);
        $documents->entries['file:///docs/a']['bytes'] = str_repeat('a', 65);
        $this->assertInternal($app, $request);
        $documents->entries['file:///docs/a'] = ['bytes' => 'a', 'size' => 2];
        $this->assertInternal($app, $request);
        $documents->returnedInfo = McpResourceInfo::fromArray(['uri' => 'file:///elsewhere', 'name' => 'wrong']);
        $this->assertInternal($app, $request);
        self::assertSame(array_fill(0, 5, 'resource_failure'), $diagnostics->events);
    }

    private function assertInternal(App $app, ServerRequestInterface $request): void
    {
        $response = $app->handle($request);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32603, 'message' => 'Internal error.']], json_decode((string) $response->getBody(), true));
    }

    private function responseResult(App $app, string $method, array $parameters = []): array
    {
        $response = $app->handle($this->request($method, $parameters));
        self::assertSame(200, $response->getStatusCode());
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['result'];
    }

    private function request(string $method, array $parameters = []): ServerRequestInterface
    {
        $headers = ['Authorization' => 'Bearer fixture-only', 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method];
        if (isset($parameters['uri'])) { $headers['Mcp-Name'] = $parameters['uri']; }
        return new ServerRequest('POST', 'https://server.test/consumer/resources', $headers, json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => [...$parameters, '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) [],
        ]]], JSON_THROW_ON_ERROR));
    }
}
