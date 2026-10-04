<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolFailureMap;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Mcp\Tool\Messaging\McpToolMetadataFilter;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Test\Common\Fixture\Mcp\DiscoveryTool;
use Fight\Test\Common\Fixture\Mcp\EndpointCapability;
use Fight\Test\Common\Fixture\Mcp\MirroredTool;
use Fight\Test\Common\Fixture\Mcp\ReadValueTool;
use Fight\Test\Common\Fixture\Mcp\WriteValueTool;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\App;
use Throwable;

#[CoversNothing]
final class McpEndpointJourneyTest extends TestCase
{
    public function test_that_consumer_owned_route_enforces_the_complete_guarded_json_journey(): void
    {
        $capability = new EndpointCapability([new McpMirrorDeclaration('tools/call', ['arguments', 'region'], 'Region')]);
        $guard = new class implements McpInvocationGuard {
            public bool $allowed = true;
            public array $calls = [];
            public function allows(string $method, ?string $name): bool
            {
                $this->calls[] = [$method, $name];
                return $this->allowed;
            }
        };
        $diagnostics = new class implements McpDiagnostics {
            public array $records = [];
            public function record(Throwable $failure): void
            {
                // The consumer deliberately records no raw throwable text or arguments.
                $this->records[] = ['event' => 'mcp_failure'];
            }
        };
        $factory = new HttpFactory();
        $endpoint = new McpRequestHandler(
            new McpCapabilityRegistry(new McpServerInfo('Journey', '1'), [$capability]),
            new ExactMcpOriginPolicy(['https://client.test']),
            $guard,
            new McpResponseFactory($factory, $factory, $diagnostics),
        );
        $app = new App($factory);
        $app->any('/consumer/mcp', fn (ServerRequestInterface $request) => $endpoint->handle($request));
        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($factory) {
            if ($request->getHeaderLine('Authorization') !== 'Bearer fixture-only') {
                return $factory->createResponse(401);
            }
            return $next->handle($request);
        });

        $request = $this->request('tools/call', ['name' => 'execute_sql', 'arguments' => ['region' => 'us-west1']])
            ->withHeader('mcp-name', 'execute_sql')->withHeader('mCP-paRaM-rEGion', 'us-west1');
        self::assertSame(401, $app->handle($request->withoutHeader('Authorization'))->getStatusCode());
        self::assertSame([], $guard->calls);
        self::assertSame(0, $capability->handleCalls);

        $response = $app->handle($request->withHeader('Origin', 'https://client.test'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(['jsonrpc' => '2.0', 'id' => 'journey', 'result' => [
            'resultType' => 'complete', 'message' => 'accepted', '_meta' => [
                'io.modelcontextprotocol/serverInfo' => ['name' => 'Journey', 'version' => '1'],
            ],
        ]], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame([['tools/call', 'execute_sql']], $guard->calls);
        self::assertSame(1, $capability->handleCalls);

        $discovery = $app->handle($this->request('server/discover'));
        self::assertSame(200, $discovery->getStatusCode());
        self::assertStringContainsString('"tools":{}', (string) $discovery->getBody());
        self::assertSame(1, $capability->handleCalls);

        foreach ([
            [$request->withHeader('Origin', 'https://evil.test'), 403, null],
            [$request->withoutHeader('Mcp-Param-Region'), 400, -32020],
            [$request->withHeader('Mcp-Param-Region', 'other'), 400, -32020],
            [$request->withAddedHeader('mcp-param-region', 'us-west1'), 400, -32020],
            [$request->withoutHeader('MCP-Protocol-Version'), 400, -32020],
            [$request->withHeader('Mcp-Method', 'tools/list'), 400, -32020],
            [$request->withHeader('Mcp-Name', 'different'), 400, -32020],
            [$request->withBody($factory->createStream('{')), 400, -32700],
            [$request->withMethod('GET'), 405, null],
            [$request->withMethod('DELETE'), 405, null],
            [$request->withHeader('Accept', 'application/json'), 406, null],
        ] as [$rejected, $status, $code]) {
            $response = $app->handle($rejected);
            self::assertSame($status, $response->getStatusCode());
            if ($code !== null) {
                self::assertSame($code, json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['error']['code']);
            }
            self::assertSame(2, count($guard->calls));
            self::assertSame(1, $capability->handleCalls);
        }

        $guard->allowed = false;
        $limited = $app->handle($request);
        self::assertSame(429, $limited->getStatusCode());
        self::assertSame(['jsonrpc' => '2.0', 'id' => 'journey', 'error' => [
            'code' => 429, 'message' => 'Invocation limit exceeded.',
        ]], json_decode((string) $limited->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame(1, $capability->handleCalls);
        self::assertSame([], $diagnostics->records);

        $guard->allowed = true;
        $capability->handlingFailure = new RuntimeException('fixture-secret /private/database');
        $failure = $app->handle($request);
        self::assertSame(500, $failure->getStatusCode());
        self::assertSame(['jsonrpc' => '2.0', 'id' => 'journey', 'error' => [
            'code' => -32603, 'message' => 'Internal error.',
        ]], json_decode((string) $failure->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame([['event' => 'mcp_failure']], $diagnostics->records);
        self::assertSame(2, $capability->handleCalls);
    }

    public function test_that_explicit_tool_discovery_runs_only_after_transport_guards_and_conceals_unavailable_tools(): void
    {
        $availability = new class implements McpToolAvailability {
            public bool $available = true;
            public bool $fail = false;
            public int $calls = 0;
            public function isAvailable(McpToolInfo $tool): bool
            {
                ++$this->calls;
                if ($this->fail) {
                    throw new RuntimeException('private policy detail');
                }
                return $this->available;
            }
        };
        $guard = new class implements McpInvocationGuard {
            public bool $allowed = true;
            public function allows(string $method, ?string $name): bool
            {
                return $this->allowed;
            }
        };
        $diagnostics = new class implements McpDiagnostics {
            public int $calls = 0;
            public function record(Throwable $failure): void
            {
                ++$this->calls;
            }
        };
        $factory = new HttpFactory();
        $discovery = new McpToolDiscovery(
            new McpToolRegistry([new DiscoveryTool()]), $availability, 'test-only-cursor-secret-00106-32-bytes',
        );
        $endpoint = new McpRequestHandler(
            new McpCapabilityRegistry(new McpServerInfo('Tool journey', '1'), [$discovery]),
            new ExactMcpOriginPolicy(['https://client.test']),
            $guard,
            new McpResponseFactory($factory, $factory, $diagnostics),
        );
        $app = new App($factory);
        $app->post('/consumer/mcp', fn (ServerRequestInterface $request) => $endpoint->handle($request));
        $request = $this->request('tools/list');
        self::assertSame(403, $app->handle($request->withHeader('Origin', 'https://evil.test'))->getStatusCode());
        self::assertSame(400, $app->handle($request->withoutHeader('Mcp-Method'))->getStatusCode());
        $guard->allowed = false;
        self::assertSame(429, $app->handle($request)->getStatusCode());
        self::assertSame(0, $availability->calls);

        $guard->allowed = true;
        $response = $app->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $result = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['result'];
        self::assertSame(['alpha.find'], array_column($result['tools'], 'name'));
        self::assertSame(['id' => ['type' => 'string']], $result['tools'][0]['inputSchema']['properties']);
        self::assertSame('private', $result['cacheScope']);
        self::assertSame(0, $result['ttlMs']);
        self::assertArrayNotHasKey('nextCursor', $result);

        $availability->available = false;
        $concealed = $app->handle($request);
        self::assertSame([], json_decode((string) $concealed->getBody(), true, flags: JSON_THROW_ON_ERROR)['result']['tools']);
        self::assertStringNotContainsString('alpha.find', (string) $concealed->getBody());
        self::assertSame(2, $availability->calls);
        self::assertSame(0, $diagnostics->calls);

        $availability->fail = true;
        $failure = $app->handle($request);
        self::assertSame(500, $failure->getStatusCode());
        self::assertSame(['jsonrpc' => '2.0', 'id' => 'journey', 'error' => [
            'code' => -32603, 'message' => 'Internal error.',
        ]], json_decode((string) $failure->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame(1, $diagnostics->calls);
    }

    public function test_that_registered_cqrs_tools_complete_the_guarded_write_read_and_failure_journey(): void
    {
        $commands = new class implements SynchronousCommandBus {
            public array $records = [];
            public array $messages = [];
            public function execute(Command $command): void { $this->dispatch(CommandMessage::create($command)); }
            public function dispatch(CommandMessage $message): void
            {
                $this->messages[] = $message;
                $this->records[$message->payload()->toArray()['value']] = ['value' => 'recorded', 'private' => 'credential-not-for-output'];
            }
        };
        $queries = new class ($commands) implements QueryBus {
            public array $messages = [];
            public function __construct(private object $commands) {}
            public function fetch(Query $query): mixed { return $this->dispatch(QueryMessage::create($query)); }
            public function dispatch(QueryMessage $message): mixed
            {
                $this->messages[] = $message;
                $id = $message->payload()->toArray()['value'];
                if ($id === 'unexpected') { throw new LogicException('credential /private/path'); }
                return $this->commands->records[$id] ?? throw new RuntimeException('private missing-record details');
            }
        };
        $metadata = new McpToolMetadataFilter();
        $commandPipeline = new CommandPipeline($commands);
        $queryPipeline = new QueryPipeline($queries);
        $commandPipeline->addFilter($metadata);
        $queryPipeline->addFilter($metadata);
        $availability = new class implements McpToolAvailability {
            public bool $mirroredAvailable = true;
            public function isAvailable(McpToolInfo $tool): bool
            {
                return $tool->name() !== 'mirrored' || $this->mirroredAvailable;
            }
        };
        $guard = new class implements McpInvocationGuard {
            public function allows(string $method, ?string $name): bool { return true; }
        };
        $diagnostics = new class implements McpDiagnostics {
            public int $calls = 0;
            public function record(Throwable $failure): void { ++$this->calls; }
        };
        $mirrored = new MirroredTool();
        $registry = new McpToolRegistry([new WriteValueTool($commandPipeline), new ReadValueTool($queryPipeline), $mirrored]);
        $factory = new HttpFactory();
        $endpoint = new McpRequestHandler(
            new McpCapabilityRegistry(new McpServerInfo('CQRS journey', '1'), [
                new McpToolDiscovery($registry, $availability, str_repeat('x', 32)),
                new McpToolInvocation(new McpToolInvoker($registry, $availability,
                    failures: new McpToolFailureMap([RuntimeException::class => 'The item is unavailable.']), metadata: $metadata)),
            ]),
            new ExactMcpOriginPolicy(['https://client.test']), $guard,
            new McpResponseFactory($factory, $factory, $diagnostics),
        );
        $app = new App($factory);
        $app->post('/consumer/mcp', fn(ServerRequestInterface $request) => $endpoint->handle($request));
        $call = fn(string $name, mixed $arguments) => $this->request('tools/call', ['name' => $name, 'arguments' => $arguments])->withHeader('Mcp-Name', $name);
        foreach ([['value.write', ['id' => 'item-1'], ['id' => 'item-1']], ['value.read', ['id' => 'item-1'], ['value' => 'recorded']]] as [$name, $arguments, $expected]) {
            $response = $app->handle($call($name, (object) $arguments));
            self::assertSame(200, $response->getStatusCode());
            $result = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['result'];
            self::assertSame($expected, $result['structuredContent']);
            self::assertSame(json_encode($expected), $result['content'][0]['text']);
            self::assertSame('complete', $result['resultType']);
            self::assertStringNotContainsString('credential', (string) $response->getBody());
        }
        self::assertCount(1, $commands->messages);
        self::assertCount(1, $queries->messages);
        self::assertSame('value.write', $commands->messages[0]->meta()->get('fight.mcp/tool'));
        self::assertSame('value.read', $queries->messages[0]->meta()->get('fight.mcp/tool'));
        self::assertArrayHasKey('item-1', $commands->records);

        foreach ([['value.write', '', 'Tool arguments do not match the input schema.'], ['value.read', 'missing', 'The item is unavailable.']] as [$name, $id, $message]) {
            $response = $app->handle($call($name, (object) ['id' => $id]));
            self::assertSame(200, $response->getStatusCode());
            $result = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['result'];
            self::assertTrue($result['isError']);
            self::assertSame($message, $result['content'][0]['text']);
        }
        self::assertCount(1, $commands->messages);
        self::assertSame(0, $diagnostics->calls);
        $unexpected = $app->handle($call('value.read', (object) ['id' => 'unexpected']));
        self::assertSame(500, $unexpected->getStatusCode());
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], json_decode((string) $unexpected->getBody(), true)['error']);
        self::assertSame(1, $diagnostics->calls);

        $mirrorRequest = $call('mirrored', (object) ['account' => (object) ['region' => 'west']]);
        self::assertSame(400, $app->handle($mirrorRequest)->getStatusCode());
        self::assertSame(0, $mirrored->calls);
        self::assertSame(200, $app->handle($mirrorRequest->withHeader('Mcp-Param-Region', 'west'))->getStatusCode());
        self::assertSame(1, $mirrored->calls);
        $availability->mirroredAvailable = false;
        $hidden = $app->handle($mirrorRequest);
        $unknown = $app->handle($call('unknown', (object) ['account' => (object) ['region' => 'west']]));
        self::assertSame((string) $hidden->getBody(), (string) $unknown->getBody());
        self::assertSame(-32602, json_decode((string) $hidden->getBody(), true)['error']['code']);
        self::assertSame(1, $diagnostics->calls);
        self::assertSame(1, $mirrored->calls);
        self::assertCount(3, $queries->messages);
    }

    private function request(string $method, array $params = []): ServerRequestInterface
    {
        return new ServerRequest('POST', 'https://server.test/consumer/mcp', [
            'Authorization' => 'Bearer fixture-only', 'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method,
        ], json_encode(['jsonrpc' => '2.0', 'id' => 'journey', 'method' => $method, 'params' => $params + ['_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) [],
        ]]], JSON_THROW_ON_ERROR));
    }
}
