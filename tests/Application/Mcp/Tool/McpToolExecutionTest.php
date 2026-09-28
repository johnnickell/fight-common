<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Closure;
use Fiber;
use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolExecution;
use Fight\Common\Application\Mcp\Tool\McpToolFailureMap;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Mcp\Tool\NullMcpProgressReporter;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\Domain\Serialization\SampleCommand;
use Fight\Test\Common\Domain\Serialization\SampleQuery;
use Fight\Test\Common\Fixture\Mcp\ProgressTool;
use Fight\Test\Common\Fixture\Mcp\ReadValueTool;
use Fight\Test\Common\Fixture\Mcp\WriteValueTool;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

#[CoversClass(McpToolExecution::class)]
#[CoversClass(McpToolInvocation::class)]
#[CoversClass(McpToolInvoker::class)]
final class McpToolExecutionTest extends UnitTestCase
{
    #[DataProvider('tokens')]
    public function test_that_progress_preserves_tokens_and_precedes_the_same_complete_response(int|string $token): void
    {
        $tool = $this->successfulTool();
        $request = $this->request($token);
        $messages = [];
        $execution = new McpToolExecution($request, static function (array $message) use (&$messages): void { $messages[] = $message; });
        $execution->run($this->responder($tool, $execution));
        self::assertSame(1, $tool->calls);
        self::assertSame([], $tool->input->toArray());
        self::assertCount(3, $messages);
        self::assertSame(['jsonrpc' => '2.0', 'method' => 'notifications/progress', 'params' => [
            'progressToken' => $token, 'progress' => 0.0, 'total' => 2.0, 'message' => 'Starting',
        ]], $messages[0]);
        self::assertSame(['jsonrpc' => '2.0', 'method' => 'notifications/progress', 'params' => [
            'progressToken' => $token, 'progress' => 1.5,
        ]], $messages[1]);
        self::assertSame(7, $messages[2]['id']);
        self::assertSame('complete', $messages[2]['result']['resultType']);
        self::assertSame('finished', $messages[2]['result']['structuredContent']);
        self::assertArrayNotHasKey('id', $messages[0]);
        self::assertArrayNotHasKey('content', $messages[0]['params']);
        self::assertArrayNotHasKey('structuredContent', $messages[0]['params']);
        $direct = $this->responder($this->successfulTool())->dispatch($this->request())->toArray();
        self::assertSame($direct, $messages[2]);
        self::assertFalse($tool->reporter->isCancelled());
    }

    public static function tokens(): iterable
    {
        yield ['token'];
        yield [''];
        yield ['0'];
        yield [0];
        yield [-12];
        yield [PHP_INT_MAX];
    }

    public function test_that_missing_token_uses_a_fresh_noop_reporter_and_one_final_per_request(): void
    {
        $tool = $this->successfulTool();
        $reporters = [];
        for ($index = 0; $index < 2; ++$index) {
            $messages = [];
            $execution = new McpToolExecution($this->request(), static function (array $message) use (&$messages): void { $messages[] = $message; });
            $execution->run($this->responder($tool, $execution));
            $reporters[] = $tool->reporter;
            self::assertInstanceOf(NullMcpProgressReporter::class, $tool->reporter);
            self::assertCount(1, $messages);
            self::assertSame('finished', $messages[0]['result']['structuredContent']);
        }
        self::assertNotSame($reporters[0], $reporters[1]);
        self::assertSame(2, $tool->calls);
    }

    public function test_that_query_and_mutation_tools_preserve_bus_payloads_and_semantic_output_with_both_reporters(): void
    {
        foreach (['value.read', 'value.write'] as $name) {
            $finals = [];
            foreach ([null, 'live'] as $token) {
                $queries = $this->mock(QueryBus::class);
                $commands = $this->mock(CommandBus::class);
                if ($name === 'value.read') {
                    $queries->shouldReceive('fetch')->once()->withArgs(fn(SampleQuery $query): bool => $query->toArray() === ['value' => 'item'])
                        ->andReturn(['value' => 'public', 'secret' => 'not-output']);
                    $tool = new ReadValueTool($queries);
                } else {
                    $commands->shouldReceive('execute')->once()->withArgs(fn(SampleCommand $command): bool => $command->toArray() === ['value' => 'item']);
                    $tool = new WriteValueTool($commands);
                }
                $messages = [];
                $execution = new McpToolExecution($this->request($token, $name, ['id' => 'item']), static function (array $message) use (&$messages): void { $messages[] = $message; });
                $execution->run($this->responder($tool, $execution));
                self::assertCount($token === null ? 1 : 3, $messages);
                $finals[] = end($messages);
                self::assertStringNotContainsString('secret', json_encode($messages));
            }
            self::assertSame($finals[0]['result']['content'], $finals[1]['result']['content']);
            self::assertEquals($finals[0], $finals[1]);
        }
    }

    #[DataProvider('invalidInputs')]
    public function test_that_input_rejection_is_complete_without_progress_or_bus_dispatch(array $arguments): void
    {
        $messages = [];
        $execution = new McpToolExecution($this->request('live', 'value.read', $arguments), static function (array $message) use (&$messages): void { $messages[] = $message; }, $this->mock(McpDiagnostics::class));
        $execution->run($this->responder(new ReadValueTool($this->mock(QueryBus::class)), $execution));
        self::assertCount(1, $messages);
        self::assertTrue($messages[0]['result']['isError']);
        self::assertSame('complete', $messages[0]['result']['resultType']);
        self::assertArrayNotHasKey('error', $messages[0]);
    }

    public static function invalidInputs(): iterable
    {
        yield [['id' => 1]];
        yield [['id' => '  ']];
    }

    public function test_that_expected_failure_after_progress_delivers_one_safe_tool_error(): void
    {
        $tool = new ProgressTool(static function (McpProgressReporter $progress): never {
            $progress->report(1);
            throw new RuntimeException('private-secret');
        });
        $messages = [];
        $execution = new McpToolExecution($this->request('live'), static function (array $message) use (&$messages): void { $messages[] = $message; }, $this->mock(McpDiagnostics::class));
        $execution->run($this->responder($tool, $execution, new McpToolFailureMap([RuntimeException::class => 'Not available.'])));
        self::assertCount(2, $messages);
        self::assertTrue($messages[1]['result']['isError']);
        self::assertSame('Not available.', $messages[1]['result']['content'][0]['text']);
        self::assertStringNotContainsString('private-secret', json_encode($messages));
    }

    #[DataProvider('lateFailures')]
    public function test_that_late_failures_are_diagnosed_once_and_sanitized(Closure $behavior): void
    {
        $tool = new ProgressTool($behavior);
        $messages = [];
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once()->withArgs(fn(Throwable $failure): bool => $failure instanceof DomainException);
        $execution = new McpToolExecution($this->request('live'), static function (array $message) use (&$messages): void { $messages[] = $message; }, $diagnostics);
        $execution->run($this->responder($tool, $execution));
        self::assertCount(2, $messages);
        self::assertSame('notifications/progress', $messages[0]['method']);
        self::assertSame(['jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32603, 'message' => 'Internal error.']], $messages[1]);
    }

    public static function lateFailures(): iterable
    {
        yield 'unexpected' => [static function (McpProgressReporter $progress): never { $progress->report(1); throw new RuntimeException('secret'); }];
        yield 'output-schema' => [static function (McpProgressReporter $progress): McpToolOutput { $progress->report(1); return McpToolOutput::structured(123); }];
        yield 'duplicate-progress' => [static function (McpProgressReporter $progress): never { $progress->report(1); $progress->report(1); throw new RuntimeException('unreachable'); }];
    }

    #[DataProvider('invalidReports')]
    public function test_that_invalid_status_is_never_delivered_or_disguised_as_a_mapped_tool_error(float $progress, ?float $total, ?string $message): void
    {
        $tool = new ProgressTool(static function (McpProgressReporter $reporter) use ($progress, $total, $message): McpToolOutput {
            $reporter->report(1);
            $reporter->report($progress, $total, $message);
            return McpToolOutput::structured('unreachable');
        });
        $messages = [];
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $execution = new McpToolExecution($this->request('live'), static function (array $message) use (&$messages): void { $messages[] = $message; }, $diagnostics);
        $execution->run($this->responder($tool, $execution, new McpToolFailureMap([DomainException::class => 'Do not map reporter failures.'])));
        self::assertCount(2, $messages);
        self::assertSame(-32603, $messages[1]['error']['code']);
    }

    public static function invalidReports(): iterable
    {
        yield [1, null, null];
        yield [0, null, null];
        yield [-1, null, null];
        yield [INF, null, null];
        yield [NAN, null, null];
        yield [2, 1, null];
        yield [2, INF, null];
        yield [2, NAN, null];
        yield [2, null, "\xFF"];
    }

    public function test_that_swallowed_report_failure_still_prevents_success_and_later_reports(): void
    {
        $tool = new ProgressTool(static function (McpProgressReporter $progress): McpToolOutput {
            $progress->report(1);
            foreach ([1, 2] as $value) {
                try { $progress->report($value); } catch (DomainException) {}
            }
            return McpToolOutput::structured('not success');
        });
        $messages = [];
        $execution = new McpToolExecution($this->request('live'), static function (array $message) use (&$messages): void { $messages[] = $message; });
        $execution->run($this->responder($tool, $execution));
        self::assertCount(2, $messages);
        self::assertSame(-32603, $messages[1]['error']['code']);
    }

    public function test_that_structured_progress_content_cannot_enter_the_status_contract(): void
    {
        $tool = new ProgressTool(static function (McpProgressReporter $progress): McpToolOutput {
            $progress->report(1);
            $progress->report(2, null, ['partial' => 'result']);
            return McpToolOutput::structured('unreachable');
        });
        $messages = [];
        $execution = new McpToolExecution($this->request('live'), static function (array $message) use (&$messages): void { $messages[] = $message; });
        $execution->run($this->responder($tool, $execution));
        self::assertCount(2, $messages);
        self::assertSame(-32603, $messages[1]['error']['code']);
    }

    public function test_that_early_cancellation_stops_mutation_at_its_safe_point_and_suppresses_final(): void
    {
        $messages = [];
        $execution = new McpToolExecution($this->request('live', 'value.write', ['id' => 'item']), static function (array $message) use (&$messages, &$execution): void {
            $messages[] = $message;
            $execution->cancel();
        });
        $execution->run($this->responder(new WriteValueTool($this->mock(CommandBus::class)), $execution));
        self::assertCount(1, $messages);
        self::assertTrue($execution->isCancelled());
        $execution->report(INF, NAN, "\xFF");
        self::assertCount(1, $messages);
    }

    public function test_that_cancellation_after_dispatch_does_not_undo_a_command_or_deliver_later_status(): void
    {
        $messages = [];
        $execution = new McpToolExecution($this->request('live', 'value.write', ['id' => 'item']), static function (array $message) use (&$messages): void { $messages[] = $message; });
        $commands = $this->mock(CommandBus::class);
        $commands->shouldReceive('execute')->once()->andReturnUsing(static function () use ($execution): void { $execution->cancel(); });
        $execution->run($this->responder(new WriteValueTool($commands), $execution));
        self::assertCount(1, $messages);
        self::assertTrue($execution->isCancelled());
        self::assertSame(0.0, $messages[0]['params']['progress']);
    }

    public function test_that_unexpected_failure_after_cancellation_is_server_side_only(): void
    {
        $messages = [];
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once()->andThrow(new RuntimeException('diagnostic secret'));
        $execution = new McpToolExecution($this->request('live'), static function (array $message) use (&$messages, &$execution): void { $messages[] = $message; $execution->cancel(); }, $diagnostics);
        $tool = new ProgressTool(static function (McpProgressReporter $progress): never { $progress->report(1); throw new RuntimeException('secret'); });
        $execution->run($this->responder($tool, $execution));
        self::assertCount(1, $messages);
        self::assertTrue($execution->isCancelled());
    }

    public function test_that_pre_cancelled_execution_invokes_nothing(): void
    {
        $tool = $this->successfulTool();
        $messages = [];
        $execution = new McpToolExecution($this->request('live'), static function (array $message) use (&$messages): void { $messages[] = $message; });
        $execution->cancel();
        $execution->run($this->responder($tool, $execution));
        self::assertSame([], $messages);
        self::assertSame(0, $tool->calls);
    }

    public function test_that_completion_and_cancellation_interleavings_are_request_scoped(): void
    {
        $first = [];
        $second = [];
        $execution = new McpToolExecution($this->request('same-token'), static function (array $message) use (&$first): void { $first[] = $message; });
        $tool = new ProgressTool(static function (McpProgressReporter $progress): McpToolOutput {
            $progress->report(0);
            Fiber::suspend();
            $progress->report(1);
            return McpToolOutput::structured('done');
        });
        $responder = $this->responder($tool, $execution);
        $fiber = new Fiber(static fn() => $execution->run($responder));
        $fiber->start();
        $other = new McpToolExecution($this->request('same-token'), static function (array $message) use (&$second, &$other): void {
            $second[] = $message;
            if (isset($message['result'])) { $other->cancel(); }
        });
        $other->run($this->responder($this->successfulTool(), $other));
        $execution->cancel();
        $fiber->resume();
        self::assertTrue($fiber->isTerminated());
        self::assertCount(1, $first);
        self::assertCount(3, $second);
        self::assertTrue($tool->reporter->isCancelled());
        self::assertFalse($other->isCancelled());
        try { $other->report(3); self::fail('Late report must reject.'); } catch (DomainException) {}
        try { $execution->run($responder); self::fail('Duplicate run must reject.'); } catch (DomainException) {}
        self::assertCount(1, $first);
        self::assertCount(3, $second);
    }

    public function test_that_reentrant_reports_cannot_reorder_delivery_or_turn_violation_into_success(): void
    {
        $messages = [];
        $execution = new McpToolExecution($this->request('live'), static function (array $message) use (&$messages, &$execution): void {
            $messages[] = $message;
            if (isset($message['method'])) {
                try { $execution->report(2); } catch (DomainException) {}
            }
        });
        $execution->run($this->responder($this->successfulTool(), $execution));
        self::assertCount(2, $messages);
        self::assertSame(-32603, $messages[1]['error']['code']);
    }

    public function test_that_final_delivery_is_not_retried_and_reports_are_already_closed(): void
    {
        $finalAttempts = 0;
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $execution = new McpToolExecution($this->request('live'), static function (array $message) use (&$finalAttempts, &$execution): void {
            if (!isset($message['result'])) { return; }
            ++$finalAttempts;
            try { $execution->report(5); self::fail('Final delivery already closes progress.'); } catch (DomainException) {}
            throw new RuntimeException('delivery failed');
        }, $diagnostics);
        try { $execution->run($this->responder($this->successfulTool(), $execution)); self::fail('Terminal failure must propagate.'); } catch (RuntimeException $failure) {
            self::assertSame('delivery failed', $failure->getMessage());
        }
        self::assertSame(1, $finalAttempts);
    }

    public function test_that_progress_delivery_failure_cannot_be_mapped_and_broken_final_delivery_is_not_diagnosed_twice(): void
    {
        $attempts = 0;
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $execution = new McpToolExecution($this->request('live'), static function () use (&$attempts): never { ++$attempts; throw new RuntimeException('delivery secret'); }, $diagnostics);
        try { $execution->run($this->responder($this->successfulTool(), $execution, new McpToolFailureMap([DomainException::class => 'not a business failure']))); self::fail('Delivery unavailable.'); } catch (RuntimeException) {}
        self::assertSame(2, $attempts);
    }

    public function test_that_response_preparation_failure_after_progress_is_a_single_safe_error(): void
    {
        $request = $this->request('live');
        $messages = [];
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $execution = new McpToolExecution($request, static function (array $message) use (&$messages): void { $messages[] = $message; }, $diagnostics);
        $capability = new class($execution) implements McpCapability {
            public function __construct(private McpToolExecution $execution) {}
            public function methods(): array { return ['tools/call', 'tools/list']; }
            public function capabilities(): array { return ['tools' => ['listChanged' => false]]; }
            public function mirrorDeclarations(): array { return []; }
            public function validate(McpRequest $request): void {}
            public function handle(McpRequest $request): McpResult {
                $this->execution->reporterFor($request)->report(1);
                return McpResult::complete(['_meta' => 12]);
            }
        };
        $execution->run(new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [$capability])));
        self::assertCount(2, $messages);
        self::assertSame(-32603, $messages[1]['error']['code']);
    }

    public function test_that_unavailable_tool_rejection_has_no_progress_and_no_diagnostic(): void
    {
        $tool = $this->successfulTool();
        $availability = $this->mock(McpToolAvailability::class);
        $availability->shouldReceive('isAvailable')->once()->andReturnFalse();
        $messages = [];
        $execution = new McpToolExecution($this->request('live'), static function (array $message) use (&$messages): void { $messages[] = $message; }, $this->mock(McpDiagnostics::class));
        $execution->run($this->responder($tool, $execution, availability: $availability));
        self::assertSame(0, $tool->calls);
        self::assertCount(1, $messages);
        self::assertSame(-32602, $messages[0]['error']['code']);
    }

    public function test_that_execution_cannot_bind_another_request_or_run_invocation_outside_its_scope(): void
    {
        $request = $this->request('live');
        $execution = new McpToolExecution($request, static function (): void {});
        $tool = $this->successfulTool();
        $registry = new McpToolRegistry([$tool]);
        $invocation = new McpToolInvocation(new McpToolInvoker($registry, $this->mock(McpToolAvailability::class)), $execution);
        try { $invocation->validate($this->request('live')); self::fail('Same token is not the same request.'); } catch (McpProtocolException) {}
        try { $invocation->handle($request); self::fail('Invocation requires a running execution.'); } catch (DomainException) {}
        try { $execution->report(1); self::fail('Cannot report before execution.'); } catch (DomainException) {}
        self::assertSame(0, $tool->calls);
    }

    public function test_that_execution_rejects_non_tool_methods(): void
    {
        $request = $this->request('live');
        $this->expectException(DomainException::class);
        new McpToolExecution(new McpRequest(7, 'tools/list', [], $request->metadata()), static function (): void {});
    }

    #[DataProvider('invalidTokens')]
    public function test_that_invalid_tokens_reject_before_tool_invocation(mixed $token): void
    {
        $tool = $this->successfulTool();
        $wire = $this->wire($token);
        $result = $this->responder($tool)->respond($wire)->toArray();
        self::assertSame(-32602, $result['error']['code']);
        self::assertSame(0, $tool->calls);
    }

    public static function invalidTokens(): iterable
    {
        yield [null];
        yield [true];
        yield [false];
        yield [1.5];
        yield [[]];
        yield [(object) []];
    }

    public function test_that_misplaced_progress_tokens_reject_before_invocation(): void
    {
        $tool = $this->successfulTool();
        $data = json_decode($this->wire('live'), true);
        unset($data['params']['_meta']['progressToken']);
        $data['params']['progressToken'] = 'live';
        $data['params']['_meta']['io.modelcontextprotocol/clientCapabilities'] = (object) [];
        $result = $this->responder($tool)->respond(json_encode($data, JSON_THROW_ON_ERROR))->toArray();
        self::assertSame(-32602, $result['error']['code']);
        self::assertSame(0, $tool->calls);
    }

    private function successfulTool(): ProgressTool
    {
        return new ProgressTool(static function (McpProgressReporter $progress): McpToolOutput {
            $progress->report(0, 2, 'Starting');
            $progress->report(1.5);
            return McpToolOutput::structured('finished');
        });
    }

    private function responder(McpTool $tool, ?McpToolExecution $execution = null, ?McpToolFailureMap $failures = null, ?McpToolAvailability $availability = null): McpResponder
    {
        $registry = new McpToolRegistry([$tool]);
        $availability ??= new class implements McpToolAvailability {
            public function isAvailable(McpToolInfo $tool): bool { return true; }
        };
        return new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [
            new McpToolDiscovery($registry, $availability, str_repeat('x', 32)),
            new McpToolInvocation(new McpToolInvoker($registry, $availability, failures: $failures ?? new McpToolFailureMap()), $execution),
        ]));
    }

    private function request(int|string|null $token = null, string $name = 'progress', array $arguments = []): McpRequest
    {
        $data = json_decode($this->wire($token), true);
        if ($token === null) { unset($data['params']['_meta']['progressToken']); }
        $data['params']['_meta']['io.modelcontextprotocol/clientCapabilities'] = (object) [];
        $data['params']['name'] = $name;
        $data['params']['arguments'] = (object) $arguments;
        return new McpRequestDecoder()->decode(json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function wire(mixed $token): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => [
            'name' => 'progress', '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => (object) [], 'progressToken' => $token],
        ]], JSON_THROW_ON_ERROR);
    }
}
