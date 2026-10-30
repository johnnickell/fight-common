<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Adapter\Mcp\Http\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Mcp\Http\McpRequestHandler;
use Fight\Common\Adapter\Mcp\Http\McpResponseFactory;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Task\McpTasks;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Throwable;

final class TaskEndpoint implements McpDiagnostics, McpInvocationGuard, McpToolAvailability
{
    public array $diagnostics = [];
    public array $guardCalls = [];
    public bool $allowed = true;
    public bool $available = true;
    public readonly TaskTool $tool;
    public readonly McpTasks $tasks;
    public readonly McpToolInvoker $invoker;
    public readonly McpToolInvocation $invocation;
    public readonly McpCapabilityRegistry $registry;
    public readonly McpRequestHandler $handler;

    public function __construct(TaskFixture $fixture, int $budget = McpTasks::MAX_RESPONSE_BYTES)
    {
        $this->tool = new TaskTool($fixture);
        $this->tasks = new McpTasks($fixture->queries, $fixture->commands, 'scope', 'caller', $budget);
        $tools = new McpToolRegistry([$this->tool]);
        $this->invoker = new McpToolInvoker($tools, $this, tasks: $this->tasks);
        $this->invocation = new McpToolInvocation($this->invoker);
        $this->registry = new McpCapabilityRegistry(new McpServerInfo('Task fixture', '1'), [
            new McpToolDiscovery($tools, $this, str_repeat('task-fixture-key-', 4)), $this->invocation, $this->tasks
        ]);
        $factory = new HttpFactory();
        $this->handler = new McpRequestHandler($this->registry, new ExactMcpOriginPolicy(['https://example.test']), $this, new McpResponseFactory($factory, $factory, $this));
    }

    public function record(Throwable $failure): void { $this->diagnostics[] = $failure::class; }
    public function allows(string $method, ?string $name): bool { $this->guardCalls[] = [$method, $name]; return $this->allowed; }
    public function isAvailable(McpToolInfo $tool): bool { return $this->available; }

    public static function request(string $method = 'tools/call', array $params = ['name' => 'example.operation'], mixed $capabilities = null): ServerRequest
    {
        $capabilities ??= (object) ['extensions' => (object) [McpTasks::EXTENSION => (object) []], 'elicitation' => (object) ['form' => (object) []]];
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'Origin' => 'https://example.test', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method];
        if ($method === 'tools/call') { $headers['Mcp-Name'] = $params['name'] ?? 'example.operation'; }
        elseif (in_array($method, ['tasks/get', 'tasks/update', 'tasks/cancel'], true)) { $headers['Mcp-Name'] = $params['taskId'] ?? ''; }
        return new ServerRequest('POST', 'http://localhost/mcp', $headers, json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $params + ['_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => $capabilities]]], JSON_THROW_ON_ERROR));
    }

    public function call(string $method = 'tools/call', array $params = ['name' => 'example.operation'], mixed $capabilities = null): array
    {
        return json_decode((string) $this->handler->handle(self::request($method, $params, $capabilities))->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
