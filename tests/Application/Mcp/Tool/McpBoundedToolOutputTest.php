<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Fight\Common\Application\Execution\Session\Data\ExecutionSummary;
use Fight\Common\Application\Mcp\Execution\SummarizeExecutionsTool;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpJsonResponse;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(McpToolOutput::class)]
#[CoversClass(McpToolInvoker::class)]
#[CoversClass(McpResult::class)]
#[CoversClass(McpJsonResponse::class)]
final class McpBoundedToolOutputTest extends UnitTestCase
{
    public function test_that_actual_encoded_response_bound_counts_text_escaping_identity_and_central_metadata(): void
    {
        $tool = new class implements McpTool {
            public ?int $budget = null;
            public string $value = '雪 " \\ /';
            #[McpToolInfo('bounded', 'Safe fixture', ['type' => 'object', 'additionalProperties' => false], ['type' => 'object', 'required' => ['value'], 'properties' => ['value' => ['type' => 'string']], 'additionalProperties' => false])]
            public function handle(ApplicationData $data, McpProgressReporter $progress): McpToolOutput
            {
                return $this->budget === null ? McpToolOutput::structured(['value' => $this->value]) : McpToolOutput::boundedStructured(['value' => $this->value], $this->budget);
            }
        };
        $available = new class implements McpToolAvailability { public function isAvailable(McpToolInfo $tool): bool { return true; } };
        $diagnostics = new class implements McpDiagnostics { public array $failures = []; public function record(Throwable $failure): void { $this->failures[] = $failure; } };
        $registry = new McpToolRegistry([$tool]);
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('雪 \\ "', '1'), [
            new McpToolDiscovery($registry, $available, str_repeat('key-', 8)),
            new McpToolInvocation(new McpToolInvoker($registry, $available)),
        ]), diagnostics: $diagnostics);
        $request = static fn(string $id): string => json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => ['name' => 'bounded', 'arguments' => (object) [], '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR);
        $baseline = $responder->respond($request('id雪"'))->toJson();
        $decoded = json_decode($baseline, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['value' => $tool->value], $decoded['result']['structuredContent']);
        self::assertSame(['value' => $tool->value], json_decode($decoded['result']['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR));
        self::assertGreaterThan(strlen(json_encode($decoded['result'], JSON_THROW_ON_ERROR)), strlen($baseline));
        $tool->budget = strlen($baseline);
        self::assertSame($baseline, $responder->respond($request('id雪"'))->toJson(), 'Exact final byte budget is inclusive');
        --$tool->budget;
        $failed = $responder->respond($request('id雪"'))->toArray();
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $failed['error']);
        self::assertArrayNotHasKey('result', $failed);
        self::assertCount(1, $diagnostics->failures);
        ++$tool->budget;
        self::assertSame(-32603, $responder->respond($request('id雪"longer'))->errorCode(), 'Request identity is inside the bound');
        $tool->budget = 67108864;
        self::assertArrayHasKey('result', $responder->respond($request('id'))->toArray());
    }

    public function test_that_execution_output_schema_rejects_unexpected_typed_projection_fields_at_runtime(): void
    {
        $queries = $this->mock(QueryBus::class);
        $queries->shouldReceive('fetch')->once()->andReturn(new ExecutionSummary(0, ['body' => 1], 0, 0, [], null));
        $registry = new McpToolRegistry([new SummarizeExecutionsTool($queries)]);
        $available = new class implements McpToolAvailability { public function isAvailable(McpToolInfo $tool): bool { return true; } };
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Fixture', '1'), [
            new McpToolDiscovery($registry, $available, str_repeat('key-', 8)),
            new McpToolInvocation(new McpToolInvoker($registry, $available)),
        ]));
        $request = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'execution.summarize', 'arguments' => ['projects' => []], '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR);
        $response = $responder->respond($request)->toArray();
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $response['error']);
        self::assertArrayNotHasKey('result', $response);
        self::assertStringNotContainsString('body', json_encode($response, JSON_THROW_ON_ERROR));
    }

    public function test_that_invalid_output_budgets_fail_at_construction(): void
    {
        foreach ([0, -1, 67108865] as $limit) {
            try { McpToolOutput::boundedStructured(null, $limit); self::fail('Invalid budget'); }
            catch (DomainException) { self::assertTrue(true); }
        }
        self::assertSame(1, McpToolOutput::boundedStructured(null, 1)->maxEncodedResponseBytes());
        self::assertNull(McpToolOutput::structured(null)->maxEncodedResponseBytes());
    }

    public function test_that_result_response_budget_survives_metadata_and_rejects_nonpositive_values(): void
    {
        $result = McpResult::complete(['safe' => true])->withResponseLimit(1000)->withMetadata(['extra' => 'snow 雪']);
        self::assertSame(1000, $result->maxEncodedResponseBytes());
        self::assertStringContainsString('extra', McpJsonResponse::success(1, $result)->toJson());
        $this->expectException(DomainException::class);
        $result->withResponseLimit(0);
    }
}
