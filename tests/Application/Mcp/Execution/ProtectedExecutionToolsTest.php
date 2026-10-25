<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Execution;

use Fight\Common\Application\Execution\Detail\Data\CapturedDetail;
use Fight\Common\Application\Execution\Detail\Data\ContentChunk;
use Fight\Common\Application\Execution\Detail\Data\DetailReference;
use Fight\Common\Application\Execution\Detail\Data\MessagePage;
use Fight\Common\Application\Mcp\Execution\GetCapturedDetailTool;
use Fight\Common\Application\Mcp\Execution\ListCapturedMessagesTool;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Mcp\Tool\McpToolSchemaValidator;
use Fight\Common\Application\Mcp\Tool\NullMcpProgressReporter;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Detail\DetailSelection;
use Fight\Common\Domain\Execution\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Execution\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;

#[CoversClass(GetCapturedDetailTool::class)]
#[CoversClass(ListCapturedMessagesTool::class)]
final class ProtectedExecutionToolsTest extends UnitTestCase
{
    private const array SELECTION = ['project_id' => 'project', 'session_id' => 'session', 'phase_id' => 'phase', 'producer_id' => 'instrumentation', 'activity_id' => '10000000-0000-4000-8000-000000000001'];
    private const string DETAIL = '20000000-0000-4000-8000-000000000001';

    public function test_that_explicit_tools_fetch_shared_queries_with_defaults_and_supplied_bounds_and_preserve_typed_output(): void
    {
        $selection = DetailSelection::fromArray(self::SELECTION);
        $observation = ObservationFixture::detail()->recorded(new \DateTimeImmutable('2026-10-22T12:00:00.000001Z'));
        $detail = new CapturedDetail(new DetailReference($observation, $observation->detail(), true), true, ContentChunk::fromBody('雪 " \\ /', 0, 4));
        foreach ([
            [ListCapturedMessagesTool::class, self::SELECTION, new ListCapturedMessages($selection, orderings: ['recorded_at' => 'DESC']), new MessagePage([$detail], 1, 1, 32)],
            [ListCapturedMessagesTool::class, self::SELECTION + ['page' => 2, 'per_page' => 1, 'direction' => 'ASC'], new ListCapturedMessages($selection, 2, 1, ['recorded_at' => 'ASC']), new MessagePage([], 1, 2, 1)],
            [ListCapturedMessagesTool::class, self::SELECTION, new ListCapturedMessages($selection, orderings: ['recorded_at' => 'DESC']), null],
            [GetCapturedDetailTool::class, self::SELECTION + ['detail_id' => self::DETAIL, 'kind' => 'message'], GetCapturedDetail::fromArray(['selection' => self::SELECTION, 'detail_id' => self::DETAIL, 'kind' => 'message', 'offset' => 0, 'length' => 4096]), $detail],
            [GetCapturedDetailTool::class, self::SELECTION + ['detail_id' => self::DETAIL, 'kind' => 'process_output', 'offset' => 12, 'length' => 7], GetCapturedDetail::fromArray(['selection' => self::SELECTION, 'detail_id' => self::DETAIL, 'kind' => 'process_output', 'offset' => 12, 'length' => 7]), null]
        ] as [$class, $arguments, $expected, $result]) {
            $bus = $this->mock(QueryBus::class);
            $bus->shouldReceive('fetch')->once()->withArgs(static function (Query $query) use ($expected): bool {
                self::assertSame($expected::class, $query::class);
                self::assertSame($expected->toArray(), $query->toArray());
                return true;
            })->andReturn($result);
            $output = new $class($bus)->handle(new ApplicationData($arguments), new NullMcpProgressReporter());
            self::assertEquals(StrictJson::fromData($result?->toArray()), $output->structuredContent());
            self::assertSame($output->structuredContent()->toString(), $output->text());
            self::assertSame(1048576, $output->maxEncodedResponseBytes());
            $info = new ReflectionMethod($class, 'handle')->getAttributes(McpToolInfo::class)[0]->newInstance();
            self::assertTrue(McpToolSchemaValidator::matches($output->structuredContent()->toData(), $info->outputSchema()));
        }
    }

    public function test_that_invalid_protected_output_and_query_failures_use_sanitized_protocol_errors_not_empty_success(): void
    {
        $available = new class implements McpToolAvailability { public function isAvailable(McpToolInfo $tool): bool { return true; } };
        $diagnostics = new class implements McpDiagnostics { public array $failures = []; public function record(Throwable $failure): void { $this->failures[] = 'sanitized_failure'; } };
        foreach ([new MessagePage([], -1, 1, 32), new RuntimeException('PROTECTED-QUERY-FAILURE')] as $outcome) {
            $bus = $this->mock(QueryBus::class);
            $expectation = $bus->shouldReceive('fetch')->once();
            if ($outcome instanceof Throwable) { $expectation->andThrow($outcome); }
            else { $expectation->andReturn($outcome); }
            $registry = new McpToolRegistry([new ListCapturedMessagesTool($bus)]);
            $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Fixture', '1'), [new McpToolDiscovery($registry, $available, str_repeat('key-', 8)), new McpToolInvocation(new McpToolInvoker($registry, $available))]), diagnostics: $diagnostics);
            $response = $responder->respond(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'execution.messages', 'arguments' => self::SELECTION, '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR))->toArray();
            self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $response['error']);
            self::assertArrayNotHasKey('result', $response);
            self::assertStringNotContainsString('PROTECTED', json_encode($response, JSON_THROW_ON_ERROR));
        }
        self::assertSame(['sanitized_failure', 'sanitized_failure'], $diagnostics->failures);
    }

    public function test_that_direct_calls_reject_forged_fields_malformed_identity_kind_and_unbounded_ranges_before_fetch(): void
    {
        $bus = $this->mock(QueryBus::class); $bus->shouldNotReceive('fetch');
        foreach ([
            [ListCapturedMessagesTool::class, self::SELECTION + ['principal' => 'human']],
            [ListCapturedMessagesTool::class, array_replace(self::SELECTION, ['activity_id' => str_repeat('x', 36)])],
            [ListCapturedMessagesTool::class, self::SELECTION + ['per_page' => 33]],
            [ListCapturedMessagesTool::class, self::SELECTION + ['page' => 1000001, 'per_page' => 32]],
            [GetCapturedDetailTool::class, self::SELECTION + ['detail_id' => self::DETAIL, 'kind' => 'message', 'access' => true]],
            [GetCapturedDetailTool::class, self::SELECTION + ['detail_id' => str_repeat('x', 36), 'kind' => 'message']],
            [GetCapturedDetailTool::class, self::SELECTION + ['detail_id' => self::DETAIL, 'kind' => 'sql']],
            [GetCapturedDetailTool::class, self::SELECTION + ['detail_id' => self::DETAIL, 'kind' => 'message', 'length' => 4097]],
            [GetCapturedDetailTool::class, self::SELECTION + ['detail_id' => self::DETAIL, 'kind' => 'message', 'offset' => 65537]]
        ] as [$class, $arguments]) {
            try { new $class($bus)->handle(new ApplicationData($arguments), new NullMcpProgressReporter()); self::fail('Invalid protected input must reject before fetch'); }
            catch (DomainException) { self::assertTrue(true); }
        }
    }
}
