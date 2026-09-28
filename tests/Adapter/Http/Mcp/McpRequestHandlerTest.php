<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Mcp;

use Fight\Common\Adapter\Http\Mcp\DenyAllMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpJsonResponse;
use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\McpOriginPolicy;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Test\Common\Fixture\Mcp\EndpointCapability;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TypeError;

#[CoversClass(McpRequestHandler::class)]
#[CoversClass(McpResponseFactory::class)]
#[CoversClass(McpJsonResponse::class)]
#[CoversClass(McpProtocolError::class)]
final class McpRequestHandlerTest extends UnitTestCase
{
    public function test_that_missing_required_policies_fail_at_composition(): void
    {
        $registry = new McpCapabilityRegistry(new McpServerInfo('Test', '1'), []);
        $responses = $this->responses();
        foreach (['origin', 'guard'] as $missing) {
            try {
                new McpRequestHandler($registry, $missing === 'origin' ? null : new DenyAllMcpOriginPolicy(), $missing === 'guard' ? null : $this->createStub(McpInvocationGuard::class), $responses);
                self::fail('A required policy was omitted.');
            } catch (TypeError) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_that_discovery_and_dispatch_are_direct_json_without_session_state(): void
    {
        $capability = new EndpointCapability();
        $guard = $this->createMock(McpInvocationGuard::class);
        $guard->expects(self::exactly(2))->method('allows')->willReturnCallback(function (string $method, ?string $name): bool {
            self::assertContains($method, ['server/discover', 'example/echo']);
            self::assertNull($name);
            return true;
        });
        $handler = $this->handler($capability, $guard);
        $discovery = $handler->handle($this->request('server/discover')->withHeader('Mcp-Session-Id', 'old')->withHeader('Last-Event-ID', 'old'));
        self::assertSame(200, $discovery->getStatusCode());
        self::assertSame('application/json', $discovery->getHeaderLine('Content-Type'));
        self::assertFalse($discovery->hasHeader('Mcp-Session-Id'));
        self::assertFalse($discovery->hasHeader('Set-Cookie'));
        $data = $this->body($discovery);
        self::assertSame(['2026-07-28'], $data['result']['supportedVersions']);
        self::assertSame(['example', 'tools', 'resources', 'prompts'], array_keys($data['result']['capabilities']));
        self::assertSame(['name' => 'Test', 'version' => '1'], $data['result']['_meta']['io.modelcontextprotocol/serverInfo']);
        self::assertSame(0, $capability->handleCalls);
        self::assertSame(200, $handler->handle($this->request())->getStatusCode());
        self::assertSame(1, $capability->validateCalls);
        self::assertSame(1, $capability->handleCalls);
    }

    public function test_that_exact_origin_is_allowed_without_consulting_forwarded_headers(): void
    {
        $capability = new EndpointCapability();
        $guard = $this->createMock(McpInvocationGuard::class);
        $guard->expects(self::once())->method('allows')->willReturn(true);
        $handler = $this->handler($capability, $guard, new ExactMcpOriginPolicy(['https://client.test']));
        self::assertSame(200, $handler->handle($this->request()->withHeader('Origin', 'HTTPS://CLIENT.TEST:443'))->getStatusCode());
        self::assertSame(403, $handler->handle($this->request()->withHeader('Origin', 'https://evil.test')->withHeader('X-Forwarded-Host', 'evil.test'))->getStatusCode());
        self::assertSame(1, $capability->handleCalls);
    }

    #[DataProvider('rejectedTransports')]
    public function test_that_transport_rejections_never_read_the_body_or_invoke_the_guard(string $method, array $headers, int $status): void
    {
        $capability = new EndpointCapability();
        $guard = $this->createMock(McpInvocationGuard::class);
        $guard->expects(self::never())->method('allows');
        $handler = $this->handler($capability, $guard);
        $request = $this->mock(ServerRequestInterface::class);
        $request->shouldReceive('hasHeader')->with('Origin')->andReturn(array_key_exists('Origin', $headers));
        $request->shouldReceive('getHeader')->with('Origin')->andReturn($headers['Origin'] ?? []);
        $request->shouldReceive('getMethod')->andReturn($method);
        $request->shouldReceive('getHeader')->with('Accept')->andReturn($headers['Accept'] ?? ['application/json, text/event-stream']);
        $request->shouldReceive('getHeader')->with('Content-Type')->andReturn($headers['Content-Type'] ?? ['application/json']);
        $request->shouldNotReceive('getBody');
        $response = $handler->handle($request);
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame($status === 405 ? 'POST' : '', $response->getHeaderLine('Allow'));
        self::assertSame(0, $capability->validateCalls);
        self::assertSame(0, $capability->handleCalls);
    }

    public static function rejectedTransports(): iterable
    {
        foreach ([['null'], [''], ['https://client.test'], ['https://a.test', 'https://b.test'], []] as $origin) {
            yield ['POST', ['Origin' => $origin], 403];
        }
        yield ['GET', ['Origin' => ['null']], 403];
        foreach (['GET', 'DELETE', 'PUT', 'HEAD', 'OPTIONS'] as $method) {
            yield [$method, [], 405];
        }
        foreach ([[], ['application/json'], ['text/event-stream'], ['*/*'], ['application/*, text/*'], ['application/json;q=0, text/event-stream'], ['application/json;q=0.000, text/event-stream'], ['application/json;q=1.001, text/event-stream'], ['application/json;q=no, text/event-stream'], ['application/json;q="1", text/event-stream'], ['application/json;q=1;q=1, text/event-stream'], ['application/json;broken, text/event-stream'], ['application/json, text/event-stream, invalid'], ["application/json\n, text/event-stream"]] as $accept) {
            yield ['POST', ['Accept' => $accept], 406];
        }
        foreach ([[], ['text/plain'], ['application/json', 'application/json'], ['application/json; charset=iso-8859-1']] as $contentType) {
            yield ['POST', ['Content-Type' => $contentType], 415];
        }
    }

    #[DataProvider('validNegotiations')]
    public function test_that_negotiation_accepts_explicit_media_types_and_valid_weights(array $accept, string $contentType): void
    {
        $guard = $this->createMock(McpInvocationGuard::class);
        $guard->expects(self::once())->method('allows')->willReturn(true);
        $response = $this->handler(new EndpointCapability(), $guard)->handle($this->request()->withHeader('Accept', $accept)->withHeader('Content-Type', $contentType));
        self::assertSame(200, $response->getStatusCode());
    }

    public static function validNegotiations(): iterable
    {
        yield [['application/json', 'text/event-stream'], 'application/json'];
        yield [['APPLICATION/JSON;q=1.000, TEXT/EVENT-STREAM;q=0.1'], 'Application/JSON; charset="UTF-8"'];
        yield [['text/plain, application/json; charset=utf-8, text/event-stream'], 'application/json;charset=utf-8'];
        yield [['application/json;q=1; ext="x,;q=0", text/event-stream'], 'application/json'];
        yield [['application/json; ext="escaped\"quote", text/event-stream'], 'application/json'];
    }

    public function test_that_native_only_policy_accepts_absent_origin_and_rejects_a_supplied_origin(): void
    {
        $guard = $this->createMock(McpInvocationGuard::class);
        $guard->expects(self::once())->method('allows')->willReturn(true);
        $handler = $this->handler(new EndpointCapability(), $guard);
        self::assertSame(403, $handler->handle($this->request()->withHeader('Origin', 'https://client.test'))->getStatusCode());
        self::assertSame(200, $handler->handle($this->request())->getStatusCode());
    }

    public function test_that_guard_denial_retains_identity_and_never_selects_or_invokes_capability(): void
    {
        $capability = new EndpointCapability();
        $guard = $this->createMock(McpInvocationGuard::class);
        $guard->expects(self::once())->method('allows')->with('tools/call', '世界')->willReturn(false);
        $response = $this->handler($capability, $guard)->handle($this->request('tools/call', ['name' => '世界'])->withHeader('Mcp-Name', '=?base64?'.base64_encode('世界').'?='));
        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => 429, 'message' => 'Invocation limit exceeded.']], $this->body($response));
        self::assertSame(0, $capability->validateCalls);
        self::assertSame(0, $capability->handleCalls);
    }

    public function test_that_protocol_and_mirror_failures_precede_the_guard_and_dispatch(): void
    {
        $capability = new EndpointCapability([new McpMirrorDeclaration('example/echo', ['tenant'], 'Tenant')]);
        $guard = $this->createMock(McpInvocationGuard::class);
        $guard->expects(self::never())->method('allows');
        $handler = $this->handler($capability, $guard);
        $cases = [
            [$this->request()->withBody((new HttpFactory())->createStream('{')), -32700, null],
            [$this->request()->withBody((new HttpFactory())->createStream('{"jsonrpc":"1.0","id":0}')), -32600, 0],
            [$this->request()->withBody((new HttpFactory())->createStream('{"jsonrpc":"2.0","id":"","method":"example/echo","params":[]}')), -32602, ''],
            [$this->request()->withoutHeader('Mcp-Method'), -32020, 7],
            [$this->request()->withAddedHeader('Mcp-Method', 'example/echo'), -32020, 7],
            [$this->request('example/echo', ['tenant' => 'private']), -32020, 7],
            [$this->request('example/echo', [], '2025-11-25'), -32022, 7],
            [$this->request('example/echo', [], '2025-11-25')->withHeader('MCP-Protocol-Version', '2026-07-28'), -32020, 7],
        ];
        foreach ($cases as [$request, $code, $id]) {
            $response = $handler->handle($request);
            self::assertSame(400, $response->getStatusCode());
            $data = $this->body($response);
            self::assertSame($code, $data['error']['code']);
            self::assertSame($id, $data['id'] ?? null);
        }
        self::assertSame(0, $capability->validateCalls);
        self::assertSame(0, $capability->handleCalls);
    }

    public function test_that_unknown_methods_and_capability_protocol_failures_use_central_mapping(): void
    {
        $capability = new EndpointCapability();
        $guard = $this->createMock(McpInvocationGuard::class);
        $guard->expects(self::exactly(3))->method('allows')->willReturn(true);
        $handler = $this->handler($capability, $guard);
        $unknown = $handler->handle($this->request('initialize'));
        self::assertSame(404, $unknown->getStatusCode());
        self::assertSame(-32601, $this->body($unknown)['error']['code']);
        $capability->validationFailure = new McpProtocolException(McpProtocolError::invalidParams(), 999);
        $invalid = $handler->handle($this->request());
        self::assertSame(400, $invalid->getStatusCode());
        self::assertSame(7, $this->body($invalid)['id']);
        self::assertSame(-32602, $this->body($invalid)['error']['code']);
        $capability->validationFailure = null;
        $capability->handlingFailure = new McpProtocolException(McpProtocolError::invalidParams(), null);
        self::assertSame(-32602, $this->body($handler->handle($this->request()))['error']['code']);
        self::assertSame(1, $capability->handleCalls);
    }

    #[DataProvider('unexpectedFailures')]
    public function test_that_unexpected_failures_log_once_and_never_expose_diagnostics(string $stage): void
    {
        $failure = new TypeError('secret=private /internal/path ClassName storage');
        $capability = new EndpointCapability();
        $guard = $this->createMock(McpInvocationGuard::class);
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once()->withArgs(fn ($actual) => $stage === 'encoding' || $actual === $failure);
        $policy = $stage === 'origin' ? $this->createMock(McpOriginPolicy::class) : new DenyAllMcpOriginPolicy();
        $request = $this->request();
        if ($stage === 'origin') {
            $request = $request->withHeader('Origin', 'https://client.test');
            $policy->expects(self::once())->method('allows')->willThrowException($failure);
            $guard->expects(self::never())->method('allows');
        } elseif ($stage === 'guard') {
            $guard->expects(self::once())->method('allows')->willThrowException($failure);
        } else {
            $guard->expects(self::once())->method('allows')->willReturn(true);
            if ($stage === 'validate') {
                $capability->validationFailure = $failure;
            } elseif ($stage === 'handle') {
                $capability->handlingFailure = $failure;
            } else {
                $capability->result = ['invalid' => "\xff"];
            }
        }
        $response = $this->handler($capability, $guard, $policy, $diagnostics)->handle($request);
        self::assertSame(500, $response->getStatusCode());
        $expected = ['jsonrpc' => '2.0'];
        if ($stage !== 'origin') {
            $expected['id'] = 7;
        }
        $expected['error'] = ['code' => -32603, 'message' => 'Internal error.'];
        self::assertSame($expected, $this->body($response));
    }

    public static function unexpectedFailures(): iterable
    {
        foreach (['origin', 'guard', 'validate', 'handle', 'encoding'] as $stage) {
            yield [$stage];
        }
    }

    public function test_that_diagnostic_sink_failure_cannot_leak_into_the_public_response(): void
    {
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once()->andThrow(new RuntimeException('logging secret'));
        $response = $this->responses($diagnostics)->failure(new RuntimeException('original secret'), 1);
        self::assertSame(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => -32603, 'message' => 'Internal error.']], $this->body($response));
    }

    private function handler(EndpointCapability $capability, McpInvocationGuard $guard, ?McpOriginPolicy $policy = null, ?McpDiagnostics $diagnostics = null): McpRequestHandler
    {
        return new McpRequestHandler(new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [$capability]), $policy ?? new DenyAllMcpOriginPolicy(), $guard, $this->responses($diagnostics));
    }

    private function responses(?McpDiagnostics $diagnostics = null): McpResponseFactory
    {
        return new McpResponseFactory(new HttpFactory(), new HttpFactory(), $diagnostics ?? $this->mock(McpDiagnostics::class));
    }

    private function request(string $method = 'example/echo', array $params = [], string $version = '2026-07-28'): ServerRequestInterface
    {
        return new ServerRequest('POST', 'https://server.test/consumer-route', [
            'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => $version, 'Mcp-Method' => $method,
        ], json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $params + ['_meta' => [
            'io.modelcontextprotocol/protocolVersion' => $version, 'io.modelcontextprotocol/clientCapabilities' => (object) [],
        ]]], JSON_THROW_ON_ERROR));
    }

    private function body(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
