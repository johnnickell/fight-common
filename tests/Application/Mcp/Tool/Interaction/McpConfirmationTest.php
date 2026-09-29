<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool\Interaction;

use Closure;
use Error;
use Fight\Common\Adapter\Mcp\Sodium\SodiumMcpStateProtector;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\Interaction\McpConfirmationOutcome;
use Fight\Common\Application\Mcp\Tool\Interaction\McpConfirmationStore;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Application\Mcp\Tool\Interaction\McpToolInteraction;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolExecution;
use Fight\Common\Application\Mcp\Tool\McpToolFailureMap;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Domain\Serialization\SampleCommand;
use Fight\Test\Common\Fixture\Mcp\DestructiveValueTool;
use Fight\Test\Common\Fixture\Mcp\InteractiveValueTool;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(McpInputRequired::class)]
#[CoversClass(McpToolInteraction::class)]
#[CoversClass(McpToolInvoker::class)]
final class McpConfirmationTest extends UnitTestCase
{
    public function test_that_confirmations_bind_the_complete_state_and_consume_before_one_command(): void
    {
        $store = $this->store();
        $commands = $this->mock(CommandBus::class);
        $commands->shouldReceive('execute')->once()->withArgs(fn(SampleCommand $command): bool => $command->toArray() === ['value' => 'original']);
        $tool = new DestructiveValueTool(function (ApplicationData $input) use ($store, $commands): void {
            self::assertTrue($store->consumed);
            $commands->execute(new SampleCommand($input->get('id')));
        });
        $responder = $this->responder($tool, $store);
        $token = $this->issue($responder);
        self::assertSame(1, $store->issues);
        self::assertSame(0, $store->acquisitions);
        self::assertSame(0, $tool->resumes);
        $plaintext = $this->protector()->open($token);
        $state = StrictJson::fromString($plaintext);
        self::assertSame('confirmation', $state->get('mode'));
        self::assertSame($state->get('confirmation'), $store->id);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $store->id);
        self::assertSame(hash('sha256', $plaintext), $store->binding);
        self::assertSame($state->get('expires'), $store->expires);
        self::assertGreaterThan(time(), $store->expires);
        self::assertStringNotContainsString('original', $token);
        $result = $responder->respond($this->wire($this->retry($token), 2))->toArray();
        self::assertSame('complete', $result['result']['resultType']);
        self::assertSame(1, $tool->resumes);
        self::assertSame('original', $tool->restored->get('id'));
        self::assertSame('fixture reason', $tool->responses->get('reason')->content()->get('text'));
        $replay = $responder->respond($this->wire($this->retry($token), 3))->toArray();
        $this->assertRejected($replay);
        self::assertSame(1, $tool->resumes);
        self::assertSame(2, $store->acquisitions);
    }

    #[DataProvider('refusals')]
    public function test_that_any_decline_or_cancel_retires_all_forms_without_resume(string $action, bool $mixed): void
    {
        $store = $this->store();
        $tool = new DestructiveValueTool(static function (): void { self::fail('Refusal dispatched'); });
        $responder = $this->responder($tool, $store);
        $params = $this->retry($this->issue($responder));
        $params['inputResponses']['approval'] = ['action' => $action];
        if (!$mixed) {
            $params['inputResponses']['reason'] = ['action' => $action];
        }
        $result = $responder->respond($this->wire($params, 2))->toArray();
        self::assertSame('complete', $result['result']['resultType']);
        self::assertSame([['type' => 'text', 'text' => 'Confirmation not accepted.']], $result['result']['content']);
        self::assertArrayNotHasKey('structuredContent', $result['result']);
        self::assertTrue($store->consumed);
        self::assertSame(0, $tool->resumes);
        $this->assertRejected($responder->respond($this->wire($params, 3))->toArray());
    }

    public static function refusals(): iterable
    {
        yield ['decline', false];
        yield ['cancel', false];
        yield ['decline', true];
        yield ['cancel', true];
    }

    #[DataProvider('invalidRetries')]
    public function test_that_invalid_state_or_responses_never_acquire_or_dispatch(string $change): void
    {
        $store = $this->store();
        $tool = new DestructiveValueTool();
        $responder = $this->responder($tool, $store);
        $params = $this->retry($this->issue($responder));
        $id = 2;
        switch ($change) {
            case 'caller': $responder = $this->responder($tool, $store, caller: 'other'); break;
            case 'name': $params['name'] = 'different'; break;
            case 'arguments': $params['arguments']['id'] = 'other'; break;
            case 'same-id': $id = 1; break;
            case 'tampered': $params['requestState'] .= 'x'; break;
            case 'oversized': $params['requestState'] = str_repeat('x', 65537); break;
            case 'missing': unset($params['inputResponses']['reason']); break;
            case 'extra': $params['inputResponses']['extra'] = ['action' => 'cancel']; break;
            case 'action': $params['inputResponses']['approval']['action'] = 'approve'; break;
            case 'cancel-content': $params['inputResponses']['approval']['action'] = 'cancel'; break;
            case 'decline-content': $params['inputResponses']['approval'] = ['action' => 'decline', 'content' => null]; break;
            case 'schema': $params['inputResponses']['approval']['content']['confirm'] = 'true'; break;
            case 'rule': $params['inputResponses']['reason']['content']['text'] = '  '; break;
            case 'missing-content': unset($params['inputResponses']['approval']['content']); break;
            case 'unconfigured': $responder = $this->responder($tool, null); break;
            default:
                $state = StrictJson::fromString($this->protector()->open($params['requestState']), 70);
                $state = match ($change) {
                    'expiry' => $state->with('expires', time() - 1),
                    'mode' => $state->with('mode', 'other'),
                    'identity-type' => $state->with('confirmation', 42),
                    'identity-shape' => $state->with('confirmation', 'invalid'),
                };
                $params['requestState'] = $this->protector()->seal($state->toString());
        }
        $this->assertRejected($responder->respond($this->wire($params, $id))->toArray());
        self::assertFalse($store->consumed);
        self::assertSame(0, $store->acquisitions);
        self::assertSame(0, $tool->resumes);
    }

    public static function invalidRetries(): iterable
    {
        foreach (['caller', 'name', 'arguments', 'same-id', 'tampered', 'oversized', 'missing', 'extra', 'action', 'cancel-content', 'decline-content', 'schema', 'rule', 'missing-content', 'unconfigured', 'expiry', 'mode', 'identity-type', 'identity-shape'] as $change) {
            yield $change => [$change];
        }
    }

    #[DataProvider('acquisitionFailures')]
    public function test_that_every_store_rejection_and_failure_is_one_safe_terminal_shape(?McpConfirmationOutcome $outcome): void
    {
        $store = $this->store();
        $tool = new DestructiveValueTool();
        $responder = $this->responder($tool, $store);
        $token = $this->issue($responder);
        $store->outcome = $outcome;
        $this->assertRejected($responder->respond($this->wire($this->retry($token), 2))->toArray());
        self::assertSame(1, $store->acquisitions);
        self::assertSame(0, $tool->resumes);
    }

    public static function acquisitionFailures(): iterable
    {
        foreach (McpConfirmationOutcome::cases() as $outcome) {
            if ($outcome !== McpConfirmationOutcome::CONSUMED) {
                yield $outcome->name => [$outcome];
            }
        }
        yield 'infrastructure' => [null];
    }

    public function test_that_a_revoked_confirmation_is_unknown_before_response_validation_or_acquisition(): void
    {
        $store = $this->store();
        $tool = new DestructiveValueTool();
        $token = $this->issue($this->responder($tool, $store));
        $params = $this->retry($token);
        $params['inputResponses'] = 'invalid';
        $hidden = $this->responder($tool, $store, available: false, caller: 'wrong')->respond($this->wire($params, 2))->toArray();
        $unknown = $this->responder(null, $store)->respond($this->wire($params, 2))->toArray();
        self::assertSame($unknown, $hidden);
        self::assertSame('Unknown or unavailable tool.', $hidden['error']['message']);
        self::assertSame(0, $store->acquisitions);
        self::assertSame(0, $tool->resumes);
    }

    public function test_that_incapable_initial_and_retry_requests_never_enter_the_tool_or_store(): void
    {
        $store = $this->store();
        $tool = new DestructiveValueTool();
        $responder = $this->responder($tool, $store);
        $params = ['name' => 'value.delete', 'arguments' => null];
        $first = $responder->respond($this->wire($params, capable: false))->toArray();
        self::assertSame(-32021, $first['error']['code']);
        self::assertSame(0, $tool->calls);
        self::assertSame(0, $store->issues);
        $token = $this->issue($responder);
        $retry = $responder->respond($this->wire($this->retry($token), 2, false))->toArray();
        self::assertSame(-32021, $retry['error']['code']);
        self::assertSame(0, $store->acquisitions);
        self::assertSame(0, $tool->resumes);
    }

    #[DataProvider('issueFailures')]
    public function test_that_issue_requires_a_working_store_and_never_exposes_unregistered_state(string $failure): void
    {
        $store = $failure === 'missing' ? null : $this->store();
        if ($store !== null) {
            $store->failIssue = true;
        }
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $tool = new DestructiveValueTool();
        $result = $this->responder($tool, $store, diagnostics: $diagnostics)->respond($this->wire(['name' => 'value.delete', 'arguments' => ['id' => 'original']]))->toArray();
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $result['error']);
        self::assertSame(0, $tool->resumes);
        self::assertArrayNotHasKey('result', $result);
    }

    public static function issueFailures(): iterable
    {
        yield ['missing'];
        yield ['infrastructure'];
    }

    #[DataProvider('acquiredFailures')]
    public function test_that_acquired_state_is_never_restored_after_any_resume_outcome(string $failure): void
    {
        $store = $this->store();
        $mutations = 0;
        $tool = new DestructiveValueTool(function () use (&$mutations): void { ++$mutations; });
        $diagnostics = $this->mock(McpDiagnostics::class);
        $map = new McpToolFailureMap();
        if ($failure === 'expected') {
            $tool->failure = new RuntimeException('private rejection');
            $map = new McpToolFailureMap([RuntimeException::class => 'Safe rejection']);
        } elseif ($failure === 'throwable') {
            $tool->failure = new Error('private failure');
            $diagnostics->shouldReceive('record')->once();
        } elseif ($failure === 'response') {
            $tool->invalidOutput = true;
            $diagnostics->shouldReceive('record')->once();
        }
        $responder = $this->responder($tool, $store, diagnostics: $diagnostics, failures: $map);
        $token = $this->issue($responder);
        $first = $responder->respond($this->wire($this->retry($token), 2))->toArray();
        if ($failure === 'expected') {
            self::assertSame('Safe rejection', $first['result']['content'][0]['text']);
        } elseif ($failure === 'throwable' || $failure === 'response') {
            self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $first['error']);
        } else {
            self::assertSame('complete', $first['result']['resultType']);
        }
        self::assertTrue($store->consumed);
        self::assertSame(1, $tool->resumes);
        self::assertSame(in_array($failure, ['success', 'response'], true) ? 1 : 0, $mutations);
        $this->assertRejected($responder->respond($this->wire($this->retry($token), 3))->toArray());
        self::assertSame(1, $tool->resumes);
    }

    public static function acquiredFailures(): iterable
    {
        yield ['success'];
        yield ['expected'];
        yield ['throwable'];
        yield ['response'];
    }

    #[DataProvider('cancellationPoints')]
    public function test_that_cancellation_after_acquisition_never_releases_or_redispatches(string $point): void
    {
        $store = $this->store();
        $tool = new DestructiveValueTool(static function (): void { self::fail('Cancelled mutation dispatched'); });
        $responder = $this->responder($tool, $store);
        $token = $this->issue($responder);
        $reporter = new class implements McpProgressReporter {
            public bool $cancelled = false;
            public function report(mixed $progress, mixed $total = null, mixed $message = null): void {}
            public function isCancelled(): bool { return $this->cancelled; }
        };
        if ($point === 'before') {
            $store->afterConsume = static function () use ($reporter): void { $reporter->cancelled = true; };
        } else {
            $tool->duringResume = static function () use ($reporter): void { $reporter->cancelled = true; };
        }
        $result = $this->invoker($tool, $store)->invokeRequest(new McpRequestDecoder()->decode($this->wire($this->retry($token), 2)), $reporter);
        self::assertSame('complete', $result->toArray()['resultType']);
        self::assertTrue($store->consumed);
        self::assertSame($point === 'before' ? 0 : 1, $tool->resumes);
        $this->assertRejected($responder->respond($this->wire($this->retry($token), 3))->toArray());
        self::assertSame($point === 'before' ? 0 : 1, $tool->resumes);
    }

    public static function cancellationPoints(): iterable
    {
        yield ['before'];
        yield ['during'];
    }

    public function test_that_stream_closure_during_progress_preserves_consumption_and_suppresses_final_output(): void
    {
        $store = $this->store();
        $tool = new DestructiveValueTool(static function (): void { self::fail('Disconnected mutation dispatched'); });
        $responder = $this->responder($tool, $store);
        $token = $this->issue($responder);
        $tool->report = true;
        $wire = json_decode($this->wire($this->retry($token), 2), true);
        $wire['params']['_meta']['progressToken'] = 'progress';
        $wire['params']['_meta']['io.modelcontextprotocol/clientCapabilities']['elicitation']['form'] = (object) [];
        $request = new McpRequestDecoder()->decode(json_encode($wire));
        $frames = [];
        $execution = new McpToolExecution($request, function (array $frame) use (&$frames, &$execution): void {
            $frames[] = $frame;
            $execution->cancel(); // Simulates outer stream closure after a progress frame.
        });
        $execution->run($responder);
        self::assertCount(1, $frames);
        self::assertSame('notifications/progress', $frames[0]['method']);
        self::assertTrue($execution->isCancelled());
        self::assertTrue($store->consumed);
        self::assertSame(1, $tool->resumes);
        $this->assertRejected($responder->respond($this->wire($this->retry($token), 3))->toArray());
    }

    public function test_that_an_uncertain_store_write_cannot_restore_an_acquired_confirmation(): void
    {
        $store = $this->store();
        $tool = new DestructiveValueTool();
        $responder = $this->responder($tool, $store);
        $token = $this->issue($responder);
        $store->afterConsume = static function (): void { throw new RuntimeException('Lost acknowledgement'); };
        $this->assertRejected($responder->respond($this->wire($this->retry($token), 2))->toArray());
        self::assertTrue($store->consumed);
        self::assertSame(0, $tool->resumes);
        $this->assertRejected($responder->respond($this->wire($this->retry($token), 3))->toArray());
        self::assertSame(0, $tool->resumes);
        self::assertSame(2, $store->acquisitions); // One attempt per request, no internal retry.
    }

    public function test_that_confirmation_content_policy_remains_with_the_consumer_tool(): void
    {
        $store = $this->store();
        $tool = new DestructiveValueTool(static function (): void { self::fail('Consumer refused mutation dispatched'); });
        $responder = $this->responder($tool, $store);
        $params = $this->retry($this->issue($responder));
        $params['inputResponses']['approval']['content']['confirm'] = false;
        $result = $responder->respond($this->wire($params, 2))->toArray();
        self::assertSame('complete', $result['result']['resultType']);
        self::assertSame(1, $tool->resumes); // Accept is protocol action, not inferred business approval.
        self::assertTrue($store->consumed);
    }

    public function test_that_a_configured_store_is_never_used_for_ordinary_retries(): void
    {
        $store = $this->mock(McpConfirmationStore::class); // No issue/consume expectation.
        $tool = new InteractiveValueTool();
        $availability = new class implements McpToolAvailability {
            public function isAvailable(McpToolInfo $tool): bool { return true; }
        };
        $invoker = new McpToolInvoker(new McpToolRegistry([$tool]), $availability, interaction: new McpToolInteraction($this->protector(), 'caller', confirmations: $store));
        $initial = new McpRequestDecoder()->decode($this->wire(['name' => 'value.interactive', 'arguments' => ['id' => 'original']]));
        $issued = $invoker->invokeRequest($initial)->toArray();
        $parameters = ['name' => 'value.interactive', 'arguments' => ['id' => 'original'], 'requestState' => $issued['requestState'], 'inputResponses' => ['details' => ['action' => 'cancel']]];
        foreach ([2, 3] as $id) {
            self::assertSame('complete', $invoker->invokeRequest(new McpRequestDecoder()->decode($this->wire($parameters, $id)))->toArray()['resultType']);
        }
        self::assertSame(2, $tool->resumes);
    }

    #[DataProvider('changedBindings')]
    public function test_that_even_authentic_altered_state_cannot_acquire_another_confirmation(string $field): void
    {
        $store = $this->store();
        $tool = new DestructiveValueTool();
        $responder = $this->responder($tool, $store);
        $original = $this->issue($responder);
        $state = StrictJson::fromString($this->protector()->open($original), 70);
        $state = match ($field) {
            'confirmation' => $state->with('confirmation', str_repeat('f', 64)),
            'expires' => $state->with('expires', $state->get('expires') + 1),
            'validated' => $state->with('validated', StrictJson::fromObject(['id' => 'substituted'])),
            'id' => $state->with('id', 'other-issuer'),
        };
        $replacement = $this->protector()->seal($state->toString());
        $this->assertRejected($responder->respond($this->wire($this->retry($replacement), 2))->toArray());
        self::assertFalse($store->consumed);
        self::assertSame(1, $store->acquisitions);
        self::assertSame(0, $tool->resumes);
        self::assertSame('complete', $responder->respond($this->wire($this->retry($original), 3))->toArray()['result']['resultType']);
    }

    public static function changedBindings(): iterable
    {
        yield ['confirmation'];
        yield ['expires'];
        yield ['validated'];
        yield ['id'];
    }

    private function store(): McpConfirmationStore
    {
        // Deliberately sequential unit double; the separate process race proves actual atomic store semantics.
        return new class implements McpConfirmationStore {
            public string $id;
            public string $binding;
            public int $expires;
            public bool $consumed = false;
            public bool $failIssue = false;
            public int $issues = 0;
            public int $acquisitions = 0;
            public ?Closure $afterConsume = null;
            public ?McpConfirmationOutcome $outcome = McpConfirmationOutcome::CONSUMED;
            public function issue(string $id, string $binding, int $expires): void {
                ++$this->issues;
                if ($this->failIssue) { throw new RuntimeException('private store issue failure'); }
                $this->id = $id; $this->binding = $binding; $this->expires = $expires;
            }
            public function consume(string $id, string $binding, int $expires): McpConfirmationOutcome {
                ++$this->acquisitions;
                if ($this->id !== $id || $this->binding !== $binding || $this->expires !== $expires) { return McpConfirmationOutcome::MISMATCHED; }
                if ($this->outcome === null) { throw new RuntimeException('private store read failure'); }
                if ($this->consumed) { return McpConfirmationOutcome::ALREADY_CONSUMED; }
                if ($this->outcome === McpConfirmationOutcome::CONSUMED) {
                    $this->consumed = true;
                    if ($this->afterConsume !== null) { ($this->afterConsume)(); }
                }
                return $this->outcome;
            }
        };
    }

    private function protector(): SodiumMcpStateProtector
    {
        return new SodiumMcpStateProtector(1, [1 => str_repeat('k', 32)]);
    }

    private function invoker(?DestructiveValueTool $tool, ?McpConfirmationStore $store, bool $available = true, string $caller = 'caller', ?McpToolFailureMap $failures = null): McpToolInvoker
    {
        return new McpToolInvoker(new McpToolRegistry($tool === null ? [] : [$tool]), new class($available) implements McpToolAvailability {
            public function __construct(private bool $available) {}
            public function isAvailable(McpToolInfo $tool): bool { return $this->available; }
        }, failures: $failures ?? new McpToolFailureMap(), interaction: new McpToolInteraction($this->protector(), $caller, confirmations: $store));
    }

    private function responder(?DestructiveValueTool $tool, ?McpConfirmationStore $store, bool $available = true, string $caller = 'caller', ?McpDiagnostics $diagnostics = null, ?McpToolFailureMap $failures = null): McpResponder
    {
        $availability = new class($available) implements McpToolAvailability {
            public function __construct(private bool $available) {}
            public function isAvailable(McpToolInfo $tool): bool { return $this->available; }
        };
        return new McpResponder(new McpCapabilityRegistry(new McpServerInfo('test', '1'), [
            new McpToolDiscovery(new McpToolRegistry($tool === null ? [] : [$tool]), $availability, str_repeat('c', 32)),
            new McpToolInvocation($this->invoker($tool, $store, $available, $caller, $failures))
        ]), diagnostics: $diagnostics ?? $this->mock(McpDiagnostics::class));
    }

    private function issue(McpResponder $responder): string
    {
        $result = $responder->respond($this->wire(['name' => 'value.delete', 'arguments' => ['id' => 'original']]))->toArray();
        self::assertSame('input_required', $result['result']['resultType']);
        return $result['result']['requestState'];
    }

    private function retry(string $token): array
    {
        return ['name' => 'value.delete', 'arguments' => ['id' => 'original'], 'requestState' => $token, 'inputResponses' => [
            'approval' => ['action' => 'accept', 'content' => ['confirm' => true]],
            'reason' => ['action' => 'accept', 'content' => ['text' => 'fixture reason']]
        ]];
    }

    private function wire(array $parameters, int $id = 1, bool $capable = true): string
    {
        $parameters['_meta'] = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => $capable ? ['elicitation' => ['form' => (object) []]] : (object) []];
        return json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => $parameters], JSON_THROW_ON_ERROR);
    }

    private function assertRejected(array $result): void
    {
        self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $result['error']);
    }
}
