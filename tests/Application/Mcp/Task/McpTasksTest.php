<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Task;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Http\McpHeaderValidator;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpJsonResponse;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Task\McpTaskHandle;
use Fight\Common\Application\Mcp\Task\McpTasks;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\TaskEndpoint;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(McpTasks::class)]
#[CoversClass(McpTaskHandle::class)]
#[CoversClass(McpResult::class)]
#[CoversClass(McpToolInvoker::class)]
#[CoversClass(McpToolInvocation::class)]
#[CoversClass(McpCapabilityRegistry::class)]
#[CoversClass(McpHeaderValidator::class)]
#[CoversClass(McpResponder::class)]
final class McpTasksTest extends UnitTestCase
{
    private TaskFixture $fixture;
    private TaskEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalTaskSchema())->install($connection);
        $this->fixture = new TaskFixture($connection);
        $this->endpoint = new TaskEndpoint($this->fixture);
    }

    public function test_that_activation_start_and_get_use_flat_coherent_shapes_and_ordinary_results_remain_possible(): void
    {
        $e = $this->endpoint;
        $discovery = $e->call('server/discover', [])['result'];
        self::assertSame([], $discovery['capabilities']['extensions'][McpTasks::EXTENSION]);
        self::assertSame(['tasks/get', 'tasks/update', 'tasks/cancel'], $e->tasks->methods());
        self::assertSame([], $e->tasks->mirrorDeclarations());
        self::assertSame($e->tasks, $e->invocation->tasks());
        $result = $e->call()['result'];
        self::assertSame('task', $result['resultType']);
        self::assertSame(TaskFixture::ID, $result['taskId']);
        self::assertSame('working', $result['status']);
        self::assertSame('2026-10-27T00:00:00.000Z', $result['createdAt']);
        self::assertSame($result['createdAt'], $result['lastUpdatedAt']);
        self::assertSame(60000, $result['ttlMs']); self::assertSame(25, $result['pollIntervalMs']);
        self::assertArrayNotHasKey('state', $result); self::assertArrayNotHasKey('result', $result);
        $poll = $e->call('tasks/get', ['taskId' => TaskFixture::ID])['result'];
        self::assertSame('complete', $poll['resultType']); unset($result['resultType'], $poll['resultType']);
        self::assertSame($result, $poll);
        $e->tool->ordinary = true;
        self::assertTrue($e->call()['result']['structuredContent']['ok']);
        self::assertSame('complete', $e->call()['result']['resultType']);
    }

    #[DataProvider('states')]
    public function test_that_detailed_projection_preserves_original_outcomes_and_omits_only_absent_optional_metadata(string $status): void
    {
        $f = $this->fixture; $f->create(); $f->now += 1234;
        if ($status === 'input_required') { $f->issue(count: 2); }
        else {
            $state = match ($status) {
                'completed' => TaskFixture::completed(true),
                'failed' => TaskState::create('failed', '', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])),
                default => TaskState::create($status, 'Public 状態')
            };
            $f->record($state);
        }
        $result = $this->endpoint->call('tasks/get', ['taskId' => TaskFixture::ID])['result'];
        self::assertSame($status, $result['status']); self::assertNull($result['ttlMs']);
        self::assertArrayNotHasKey('pollIntervalMs', $result);
        self::assertSame('2026-10-27T00:00:01.234Z', $result['lastUpdatedAt']);
        if ($status === 'input_required') {
            self::assertCount(2, $result['inputRequests']);
            self::assertSame(['method' => 'elicitation/create', 'params' => ['mode' => 'form', 'message' => 'Provide input', 'requestedSchema' => ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']], 'required' => ['answer']]]], $result['inputRequests'][TaskFixture::KEY_1]);
            self::assertArrayNotHasKey('statusMessage', $result);
        } elseif ($status === 'completed') { self::assertTrue($result['result']['isError']); self::assertFalse($result['result']['structuredContent']['ok']); }
        elseif ($status === 'failed') { self::assertSame(['code' => -32603, 'message' => 'Internal error'], $result['error']); self::assertSame('', $result['statusMessage']); }
        else { self::assertSame('Public 状態', $result['statusMessage']); }
        self::assertArrayNotHasKey('definition', $result); self::assertArrayNotHasKey('version', $result);
        $created = $this->endpoint->tasks->created(new McpTaskHandle(TaskFixture::ID))->toArray();
        self::assertSame($status, $created['status']); self::assertArrayNotHasKey('result', $created); self::assertArrayNotHasKey('inputRequests', $created); self::assertArrayNotHasKey('error', $created);
    }

    public static function states(): iterable { foreach (['working', 'input_required', 'completed', 'failed', 'cancelled'] as $state) { yield $state => [$state]; } }

    public function test_that_missing_or_malformed_capabilities_precede_tool_effects_including_direct_invocation(): void
    {
        $e = $this->endpoint;
        foreach ([(object) [], (object) ['extensions' => (object) []], (object) ['extensions' => (object) [McpTasks::EXTENSION => true]], (object) ['extensions' => (object) [McpTasks::EXTENSION => []]]] as $caps) {
            self::assertContains($e->call(capabilities: $caps)['error']['code'], [-32021, -32602]);
        }
        try { $e->invoker->invoke('example.operation', (object) []); self::fail('Direct invocation has no negotiation'); }
        catch (McpProtocolException $failure) { self::assertSame(-32021, $failure->protocolError()->toArray()['code']); }
        self::assertSame(0, $e->tool->starts); self::assertNull($this->fixture->get());
        self::assertTrue($e->call(params: ['name' => 'example.operation', 'arguments' => (object) ['extra' => true]])['result']['isError']);
        self::assertSame(0, $e->tool->starts);
        $e->available = false;
        self::assertSame(-32602, $e->call()['error']['code']); self::assertSame(0, $e->tool->starts);
        $e->available = true;
        $invoker = new McpToolInvoker(new McpToolRegistry([$e->tool]), $e);
        $request = $this->decoded();
        try { $invoker->invokeRequest($request); self::fail('No partial activation'); } catch (DomainException) { self::assertSame(0, $e->tool->starts); }
    }

    public function test_that_each_task_method_requires_current_support_and_uses_its_own_mutation_admission(): void
    {
        $e = $this->endpoint; $e->call(); $this->fixture->issue();
        foreach (['tasks/get', 'tasks/update', 'tasks/cancel'] as $method) {
            self::assertSame(-32021, $e->call($method, ['taskId' => TaskFixture::ID], (object) [])['error']['code']);
        }
        self::assertSame(-32601, $e->call('tasks/list', [])['error']['code']);
        self::assertSame(-32601, $e->call('tasks/result', [])['error']['code']);
        $this->fixture->readAllowed = false;
        self::assertSame(-32602, $e->call('tasks/get', ['taskId' => TaskFixture::ID])['error']['code']);
        self::assertSame('complete', $e->call('tasks/cancel', ['taskId' => TaskFixture::ID])['result']['resultType']);
        self::assertNotNull($this->fixture->cancellation()->get('cancellation'));
        self::assertSame('complete', $e->call('tasks/update', ['taskId' => TaskFixture::ID, 'inputResponses' => (object) [TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'yes']]]])['result']['resultType']);
        $this->fixture->readAllowed = true; $this->fixture->cancelAllowed = false; $this->fixture->submitAllowed = false;
        self::assertSame(-32602, $e->call('tasks/cancel', ['taskId' => TaskFixture::ID])['error']['code']);
        self::assertSame(-32602, $e->call('tasks/update', ['taskId' => TaskFixture::ID, 'inputResponses' => (object) []])['error']['code']);
        self::assertSame('working', $e->call('tasks/get', ['taskId' => TaskFixture::ID])['result']['status']);
        try { $e->tasks->handle($this->decoded()); self::fail('Wrong method'); } catch (McpProtocolException $failure) { self::assertSame(-32601, $failure->protocolError()->toArray()['code']); }
    }

    public function test_that_invalid_missing_concealed_and_expired_records_are_safe_while_storage_failure_is_distinct(): void
    {
        $e = $this->endpoint; $e->call();
        foreach ([['taskId' => 'bad'], ['taskId' => str_repeat('b', 32)], ['taskId' => TaskFixture::ID, 'extra' => true]] as $params) { self::assertSame(-32602, $e->call('tasks/get', $params)['error']['code']); }
        $request = $this->decoded('tasks/get', []);
        try { $e->tasks->handle($request); self::fail('Missing identity'); } catch (McpProtocolException $failure) { self::assertSame(-32602, $failure->protocolError()->toArray()['code']); }
        $this->fixture->discloseAllowed = false; self::assertSame(-32602, $e->call('tasks/get', ['taskId' => TaskFixture::ID])['error']['code']);
        $this->fixture->discloseAllowed = true; $this->fixture->now += 60000; self::assertSame(-32602, $e->call('tasks/get', ['taskId' => TaskFixture::ID])['error']['code']);
        $queries = $this->mock(QueryBus::class); $commands = $this->mock(CommandBus::class);
        $tasks = new McpTasks($queries, $commands, 'scope', 'caller');
        $queries->shouldReceive('fetch')->once()->andReturn(StrictJson::fromObject(['id' => str_repeat('b', 32)]));
        try { $tasks->created(new McpTaskHandle(TaskFixture::ID)); self::fail('Mismatched Query identity'); } catch (DomainException) {}
        $queries->shouldReceive('fetch')->once()->andThrow(new RuntimeException('private storage failure'));
        try { $tasks->handle($this->decoded('tasks/get', ['taskId' => TaskFixture::ID])); self::fail('Storage errors are not absence'); } catch (RuntimeException $failure) { self::assertSame('private storage failure', $failure->getMessage()); }
    }

    public function test_that_current_creation_disclosure_and_postcommit_failures_never_fabricate_an_acknowledgment(): void
    {
        $e = $this->endpoint; $e->tool->onStart = function (): void { $this->fixture->discloseAllowed = false; };
        self::assertSame(-32603, $e->call()['error']['code']); self::assertCount(1, $e->diagnostics);
        $this->fixture->discloseAllowed = true; self::assertNotNull($this->fixture->get());
        $e->tool->onStart = static fn () => throw new RuntimeException('private submission failure');
        self::assertSame(-32603, $e->call()['error']['code']); self::assertNotNull($this->fixture->get());
        self::assertCount(2, $e->diagnostics);
    }

    public function test_that_task_mirrors_and_existing_http_guards_reject_before_mutation(): void
    {
        $e = $this->endpoint; $e->call();
        foreach (['tasks/get', 'tasks/update', 'tasks/cancel'] as $method) {
            $request = TaskEndpoint::request($method, ['taskId' => TaskFixture::ID]);
            foreach ([$request->withoutHeader('Mcp-Name'), $request->withHeader('Mcp-Name', 'wrong'), $request->withHeader('Mcp-Name', [TaskFixture::ID, TaskFixture::ID]), $request->withHeader('Mcp-Method', 'tools/call')] as $bad) {
                $response = $e->handler->handle($bad); self::assertSame(400, $response->getStatusCode());
            }
        }
        $request = TaskEndpoint::request('tasks/cancel', ['taskId' => TaskFixture::ID]);
        self::assertSame(403, $e->handler->handle($request->withHeader('Origin', 'https://evil.test'))->getStatusCode());
        $e->allowed = false; self::assertSame(429, $e->handler->handle($request)->getStatusCode());
        self::assertNull($this->fixture->cancellation()->get('cancellation'));
        self::assertSame(['tasks/cancel', TaskFixture::ID], end($e->guardCalls));
    }

    public function test_that_creation_exact_envelope_budget_and_creation_denial_do_not_claim_inaccessible_success(): void
    {
        $f = $this->fixture; $e = $this->endpoint;
        $f->createAllowed = false; self::assertSame(-32603, $e->call()['error']['code']); self::assertNull($f->get());
        $f->createAllowed = true;
        $body = (string) $e->handler->handle(TaskEndpoint::request())->getBody();
        self::assertSame($body, (string) (new TaskEndpoint($f, strlen($body)))->handler->handle(TaskEndpoint::request())->getBody());
        self::assertSame(-32603, (new TaskEndpoint($f, strlen($body) - 1))->call()['error']['code']);
        self::assertNotNull($f->get());
    }

    public function test_that_final_encoded_budget_counts_nested_escaping_id_and_central_metadata_exactly(): void
    {
        $this->fixture->create();
        $state = TaskState::create('completed', result: StrictJson::fromObject(['content' => [(object) ['type' => 'text', 'text' => str_repeat('雪"\\', 50)]], 'structuredContent' => (object) ['ok' => true], 'isError' => false]));
        $this->fixture->record($state);
        $request = TaskEndpoint::request('tasks/get', ['taskId' => TaskFixture::ID]);
        $body = (string) $this->endpoint->handler->handle($request)->getBody();
        $exact = new TaskEndpoint($this->fixture, strlen($body));
        self::assertSame($body, (string) $exact->handler->handle($request)->getBody());
        $over = new TaskEndpoint($this->fixture, strlen($body) - 1);
        self::assertSame(-32603, $over->call('tasks/get', ['taskId' => TaskFixture::ID])['error']['code']); self::assertCount(1, $over->diagnostics);
    }

    public function test_that_unsupported_methods_cannot_emit_task_results_and_partial_methods_cannot_activate(): void
    {
        $capability = $this->mock(McpCapability::class);
        $capability->shouldReceive('methods')->andReturn(['example/ping']);
        $capability->shouldReceive('capabilities')->andReturn(['example' => []]);
        $capability->shouldReceive('mirrorDeclarations')->andReturn([]);
        $capability->shouldReceive('validate')->once();
        $capability->shouldReceive('handle')->once()->andReturn(McpResult::task([]));
        $registry = new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [$capability]);
        $response = (new McpResponder($registry))->respond((string) TaskEndpoint::request('example/ping', [])->getBody());
        self::assertSame(-32603, $response->errorCode());
        $partial = $this->mock(McpCapability::class);
        $partial->shouldReceive('methods')->andReturn(['tasks/update']);
        $partial->shouldReceive('capabilities')->andReturn(['example' => []]);
        $partial->shouldReceive('mirrorDeclarations')->andReturn([]);
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [$partial]);
    }

    public function test_that_partial_or_mismatched_activation_and_invalid_budgets_reject_at_composition(): void
    {
        $e = $this->endpoint; $server = new McpServerInfo('Fixture', '1');
        $ordinary = new McpToolInvocation(new McpToolInvoker(new McpToolRegistry([]), $e));
        foreach ([[$e->tasks], [$e->tasks, $ordinary], [$e->invocation]] as $capabilities) {
            try { new McpCapabilityRegistry($server, $capabilities); self::fail('Partial composition'); } catch (DomainException) {}
        }
        foreach ([0, McpTasks::MAX_RESPONSE_BYTES + 1] as $budget) {
            try { new McpTasks($this->fixture->queries, $this->fixture->commands, 'scope', 'caller', $budget); self::fail('Invalid budget'); } catch (DomainException) {}
        }
        try { McpResult::task(['resultType' => 'complete']); self::fail('Cannot override discriminator'); } catch (DomainException) {}
        try { new McpTaskHandle('invalid'); self::fail('Invalid identity'); } catch (DomainException) {}
        self::assertSame('task', McpResult::task([])->toArray()['resultType']);
    }

    private function decoded(string $method = 'tools/call', array $params = ['name' => 'example.operation']): McpRequest
    {
        return (new McpRequestDecoder())->decode((string) TaskEndpoint::request($method, $params)->getBody());
    }
}
