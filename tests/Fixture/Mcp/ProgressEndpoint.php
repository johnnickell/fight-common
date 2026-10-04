<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Adapter\Http\Mcp\DenyAllMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolFailureMap;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use RuntimeException;
use Throwable;

final class ProgressEndpoint implements McpDiagnostics, McpInvocationGuard, McpToolAvailability
{
    public array $diagnostics = [];
    public bool $allowed = true;
    public bool $available = true;
    public int $guardCalls = 0;
    public int $availabilityCalls = 0;
    public McpRequestHandler $handler;

    public function __construct(public ProgressTool $tool, bool $progressive = true)
    {
        $factory = new HttpFactory();
        $tools = new McpToolRegistry([$tool]);
        $registry = new McpCapabilityRegistry(new McpServerInfo('Progress fixture', '1'), [
            new McpToolDiscovery($tools, $this, str_repeat('fixture-key-', 4)),
            new McpToolInvocation(new McpToolInvoker($tools, $this,
                failures: new McpToolFailureMap([RuntimeException::class => 'Unavailable.']))),
        ]);
        $this->handler = new McpRequestHandler($registry, new DenyAllMcpOriginPolicy(), $this,
            new McpResponseFactory($factory, $factory, $this), progressive: $progressive);
    }

    public function record(Throwable $failure): void { $this->diagnostics[] = $failure::class; }
    public function allows(string $method, ?string $name): bool { ++$this->guardCalls; return $this->allowed; }
    public function isAvailable(McpToolInfo $tool): bool { ++$this->availabilityCalls; return $this->available; }

    public static function request(mixed $token = 'live', string $method = 'tools/call', array $parameters = ['name' => 'progress']): ServerRequest
    {
        $metadata = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => (object) []];
        if ($token !== null) { $metadata['progressToken'] = $token; }
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method];
        if ($method === 'tools/call') { $headers['Mcp-Name'] = $parameters['name'] ?? 'progress'; }
        return new ServerRequest('POST', 'http://localhost/mcp', $headers, json_encode([
            'jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $parameters + ['_meta' => $metadata],
        ], JSON_THROW_ON_ERROR));
    }
}
