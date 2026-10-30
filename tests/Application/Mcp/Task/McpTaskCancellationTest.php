<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Task;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestMetadata;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\McpTaskCancellation;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(McpTaskCancellation::class)]
final class McpTaskCancellationTest extends UnitTestCase
{
    public function test_that_real_method_records_intent_without_read_or_input_capability_and_returns_bounded_empty_ack(): void
    {
        $f = $this->fixture(); $f->create(); $f->readAllowed = false;
        $method = new McpTaskCancellation($f->commands, 'scope', 'caller');
        $reply = $method->handle(self::request(['taskId' => TaskFixture::ID]));
        self::assertSame(['resultType' => 'complete'], $reply->toArray());
        self::assertNotNull($f->cancellation()->get('cancellation'));
        self::assertSame(2, $f->cancellation()->get('version'));
        self::assertSame(['resultType' => 'complete'], $method->handle(self::request(['taskId' => TaskFixture::ID]))->toArray());
        self::assertSame(2, $f->cancellation()->get('version'));
        self::assertSame('complete', $reply->withMetadata(['public' => 'é"'])->toArray()['resultType']);
        $this->expectException(DomainException::class);
        $reply->withMetadata(['public' => str_repeat('é"', 128)]);
    }

    public function test_that_per_request_capabilities_and_bounded_parameter_shapes_precede_mutation_and_disclosure(): void
    {
        $f = $this->fixture(); $f->create(); $method = new McpTaskCancellation($f->commands, 'scope', 'caller');
        $valid = ['taskId' => TaskFixture::ID];
        $this->failure(fn () => $method->handle(self::request($valid, method: 'tasks/update')), -32601);
        foreach ([[], ['extensions' => (object) []]] as $capabilities) {
            $error = $this->failure(fn () => $method->handle(self::request($valid, $capabilities)), -32021);
            self::assertEquals(['extensions' => ['io.modelcontextprotocol/tasks' => StrictJson::fromObject()]], $error['data']['requiredCapabilities']);
        }
        foreach ([[], ['taskId' => 1], ['taskId' => null], ['taskId' => 'guessed'], ['taskId' => str_repeat('é', 1024)], ['taskId' => str_repeat('a', 33)], $valid + ['reason' => 'private reason'], $valid + ['status' => 'cancelled'], ['taskId' => str_repeat('f', 32)]] as $bad) {
            $this->failure(fn () => $method->handle(self::request($bad)), -32602);
        }
        self::assertSame(1, $f->get()->get('version'));
        $f->cancelAllowed = false; $this->failure(fn () => $method->handle(self::request($valid)), -32602);
        $f->cancelAllowed = true; $f->cancel(); $f->cancelAllowed = false;
        $this->failure(fn () => $method->handle(self::request($valid)), -32602);
        $bus = $this->mock(CommandBus::class); $bus->shouldReceive('execute')->once()->andThrow(new TaskConflict());
        $this->failure(fn () => (new McpTaskCancellation($bus, 'scope', 'caller'))->handle(self::request($valid)), -32602);
    }

    public function test_that_internal_failures_propagate_to_central_safe_diagnostics_instead_of_success_or_invalid_identity(): void
    {
        $bus = $this->mock(CommandBus::class); $failure = new RuntimeException('private storage failure');
        $bus->shouldReceive('execute')->once()->andThrow($failure);
        try { (new McpTaskCancellation($bus, 'scope', 'caller'))->handle(self::request(['taskId' => TaskFixture::ID])); self::fail('Internal failure must not acknowledge'); }
        catch (RuntimeException $actual) { self::assertSame($failure, $actual); }
    }

    public function test_that_real_method_internal_failure_is_sanitized_by_the_existing_semantic_responder_without_advertising_tasks(): void
    {
        $failure = new RuntimeException('private database detail');
        $bus = $this->mock(CommandBus::class); $bus->shouldReceive('execute')->once()->andThrow($failure);
        $method = new McpTaskCancellation($bus, 'scope', 'caller');
        // Test-only method routing is not production extension activation or guarded HTTP qualification.
        $capability = $this->mock(McpCapability::class);
        $capability->shouldReceive('methods')->andReturn(['tasks/cancel']);
        $capability->shouldReceive('capabilities')->andReturn(['example' => []]);
        $capability->shouldReceive('mirrorDeclarations')->andReturn([]);
        $capability->shouldReceive('validate')->once();
        $capability->shouldReceive('handle')->once()->andReturnUsing(fn ($request) => $method->handle($request));
        $diagnostics = $this->mock(McpDiagnostics::class); $diagnostics->shouldReceive('record')->once()->with($failure);
        $registry = new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [$capability]);
        self::assertSame(['example' => []], $registry->advertisedCapabilities());
        $reply = (new McpResponder($registry, diagnostics: $diagnostics))->respond(json_encode([
            'jsonrpc' => '2.0', 'id' => 'cancel-failed', 'method' => 'tasks/cancel', 'params' => [
                'taskId' => TaskFixture::ID, '_meta' => [McpRequestMetadata::PROTOCOL_VERSION_KEY => '2026-07-28', McpRequestMetadata::CLIENT_CAPABILITIES_KEY => (object) ['extensions' => (object) ['io.modelcontextprotocol/tasks' => (object) []]]]
            ]
        ], JSON_THROW_ON_ERROR));
        self::assertSame(['jsonrpc' => '2.0', 'id' => 'cancel-failed', 'error' => ['code' => -32603, 'message' => 'Internal error.']], $reply->toArray());
        self::assertStringNotContainsString('private', $reply->toJson());
    }

    public static function request(array $parameters, ?array $capabilities = null, string $method = 'tasks/cancel'): McpRequest
    {
        return new McpRequest('request-1', $method, $parameters, McpRequestMetadata::fromObject(StrictJson::fromObject([
            McpRequestMetadata::PROTOCOL_VERSION_KEY => '2026-07-28',
            McpRequestMetadata::CLIENT_CAPABILITIES_KEY => StrictJson::fromObject($capabilities ?? ['extensions' => (object) ['io.modelcontextprotocol/tasks' => (object) []]])
        ])));
    }

    private function fixture(): TaskFixture { $c = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]); (new DbalTaskSchema())->install($c); return new TaskFixture($c); }
    private function failure(callable $operation, int $code): array
    {
        try { $operation(); self::fail('Expected protocol rejection'); }
        catch (McpProtocolException $failure) { $error = $failure->protocolError()->toArray(); self::assertSame($code, $error['code']); self::assertStringNotContainsString('private', json_encode($error)); return $error; }
    }
}
