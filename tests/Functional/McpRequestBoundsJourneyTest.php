<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpRequestLimits;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Test\Common\Fixture\Mcp\EndpointCapability;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\App;
use Throwable;

#[CoversNothing]
final class McpRequestBoundsJourneyTest extends TestCase
{
    public function test_that_bounded_ingress_preserves_consumer_authentication_transport_order_and_safe_dispatch(): void
    {
        $capability = new EndpointCapability();
        $guard = new class implements McpInvocationGuard {
            public int $calls = 0;
            public bool $allowed = true;
            public function allows(string $method, ?string $name): bool { ++$this->calls; return $this->allowed; }
        };
        $diagnostics = new class implements McpDiagnostics {
            public array $events = [];
            public function record(Throwable $failure): void { $this->events[] = 'request_failure'; }
        };
        $factory = new HttpFactory();
        $endpoint = new McpRequestHandler(
            new McpCapabilityRegistry(new McpServerInfo('Bounds journey', '1'), [$capability]),
            new ExactMcpOriginPolicy(['https://client.test']), $guard,
            new McpResponseFactory($factory, $factory, $diagnostics),
            new McpRequestDecoder(new McpRequestLimits(maxBytes: 512, maxDepth: 8)),
        );
        $app = new App($factory);
        $app->any('/consumer/mcp', fn (ServerRequestInterface $request) => $endpoint->handle($request));
        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($factory) {
            return $request->getHeaderLine('Authorization') === 'Bearer fixture-only'
                ? $next->handle($request) : $factory->createResponse(401);
        });

        $reads = [];
        $json = '{"jsonrpc":"2.0","id":"旅","method":"example/echo","params":{"message":"é世界","nested":[[[[[0]]]]],"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}';
        $exact = str_pad($json, 512);
        $request = $this->request($this->stream($exact, $reads));
        foreach ([
            [$request->withoutHeader('Authorization'), 401],
            [$request->withHeader('Origin', 'https://denied.test'), 403],
            [$request->withMethod('GET'), 405],
            [$request->withHeader('Accept', 'application/json'), 406],
            [$request->withHeader('Content-Type', 'text/plain'), 415],
        ] as [$rejected, $status]) {
            self::assertSame($status, $app->handle($rejected)->getStatusCode());
            self::assertSame([], $reads);
        }
        self::assertSame(0, $guard->calls);
        self::assertSame(0, $capability->handleCalls);

        $valid = $app->handle($request);
        self::assertSame(200, $valid->getStatusCode());
        self::assertSame('旅', json_decode((string) $valid->getBody(), true, flags: JSON_THROW_ON_ERROR)['id']);
        self::assertSame(512, array_sum($reads));
        self::assertSame(1, $capability->handleCalls);
        self::assertSame(1, $guard->calls);

        foreach ([null, '0', '999999999'] as $declared) {
            $reads = [];
            $request = $this->request($this->stream($exact.' '.str_repeat('private', 1000), $reads));
            if ($declared !== null) { $request = $request->withHeader('Content-Length', $declared); }
            $response = $app->handle($request);
            self::assertSame(413, $response->getStatusCode());
            self::assertSame('', (string) $response->getBody());
            self::assertSame(513, array_sum($reads));
        }

        foreach (['{', str_replace('[[[[[0]]]]]', '[[[[[[0]]]]]]', $json)] as $invalid) {
            $reads = [];
            $response = $app->handle($this->request($this->stream($invalid, $reads)));
            self::assertSame(400, $response->getStatusCode());
            self::assertSame(['jsonrpc' => '2.0', 'error' => ['code' => -32700, 'message' => 'Parse error.']], json_decode((string) $response->getBody(), true));
        }
        self::assertSame([], $diagnostics->events);
        self::assertSame(1, $guard->calls);
        self::assertSame(1, $capability->handleCalls);

        foreach (['no-progress', 'read-failure'] as $failure) {
            $readCalls = 0;
            $stream = new FnStream([
                'isSeekable' => fn () => false,
                'eof' => fn () => false,
                'read' => function (int $length) use (&$readCalls, $failure, $json) {
                    ++$readCalls;
                    // A full valid prefix is not dispatchable until EOF is established safely.
                    if ($readCalls === 1) { return $json; }
                    if ($failure === 'read-failure') { throw new RuntimeException('private body /path token'); }
                    return '';
                },
            ]);
            $response = $app->handle($this->request($stream));
            self::assertSame(500, $response->getStatusCode());
            self::assertSame(['jsonrpc' => '2.0', 'error' => ['code' => -32603, 'message' => 'Internal error.']], json_decode((string) $response->getBody(), true));
            self::assertSame(2, $readCalls);
        }
        self::assertSame(['request_failure', 'request_failure'], $diagnostics->events);
        self::assertSame(1, $guard->calls);
        self::assertSame(1, $capability->handleCalls);

        $reads = [];
        $mirror = $app->handle($this->request($this->stream($json, $reads))->withHeader('Mcp-Method', 'other'));
        self::assertSame(400, $mirror->getStatusCode());
        self::assertSame(-32020, json_decode((string) $mirror->getBody(), true)['error']['code']);
        self::assertSame(1, $guard->calls);
        $guard->allowed = false;
        $reads = [];
        self::assertSame(429, $app->handle($this->request($this->stream($json, $reads)))->getStatusCode());
        self::assertSame(2, $guard->calls);
        self::assertSame(1, $capability->handleCalls);
    }

    private function stream(string $json, array &$reads): StreamInterface
    {
        $offset = 0;
        return new FnStream([
            'isSeekable' => fn () => false,
            'eof' => function () use (&$offset, $json) { return $offset === strlen($json); },
            'read' => function (int $length) use (&$offset, &$reads, $json) {
                $chunk = substr($json, $offset, min(17, $length));
                $offset += strlen($chunk);
                $reads[] = strlen($chunk);
                return $chunk;
            },
        ]);
    }

    private function request(StreamInterface $body): ServerRequestInterface
    {
        return new ServerRequest('POST', 'https://server.test/consumer/mcp', [
            'Authorization' => 'Bearer fixture-only', 'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream', 'Mcp-Method' => 'example/echo',
            'MCP-Protocol-Version' => '2026-07-28', 'Origin' => 'https://client.test',
        ], $body);
    }
}
