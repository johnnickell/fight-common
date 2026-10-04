<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool\Interaction;

use Fight\Common\Adapter\Mcp\Sodium\SodiumMcpStateProtector;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequest;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputResponses;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInteractiveTool;
use Fight\Common\Application\Mcp\Tool\Interaction\McpStateProtector;
use Fight\Common\Application\Mcp\Tool\Interaction\McpToolInteraction;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolFailureMap;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Mcp\Tool\NullMcpProgressReporter;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Common\Application\Validation\ValidationService;
use Fight\Common\Application\Validation\Validator;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Domain\Serialization\SampleCommand;
use Fight\Test\Common\Domain\Serialization\SampleQuery;
use Fight\Test\Common\Fixture\Mcp\EchoTool;
use Fight\Test\Common\Fixture\Mcp\InteractiveValueTool;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(McpToolInteraction::class)]
#[CoversClass(McpToolInvoker::class)]
#[CoversClass(McpToolInvocation::class)]
#[CoversClass(McpToolRegistry::class)]
#[CoversClass(McpToolInfo::class)]
#[CoversClass(McpResult::class)]
#[CoversClass(McpProtocolError::class)]
final class McpToolInteractionTest extends UnitTestCase
{
    public function test_that_a_fresh_instance_resumes_protected_query_input_without_initial_validation_or_handle(): void
    {
        $queries = $this->mock(QueryBus::class);
        $first = new InteractiveValueTool($queries);
        $arguments = ['id' => 'original', 'nested' => ['b' => [true, null], 'a' => (object) []]];
        $issued = $this->responder([$first])->respond($this->wire(['name' => 'value.interactive', 'arguments' => $arguments]))->toArray();
        self::assertSame('input_required', $issued['result']['resultType']);
        self::assertSame(1, $first->calls);
        self::assertSame(0, $first->resumes);
        self::assertArrayNotHasKey('content', $issued['result']);
        self::assertArrayNotHasKey('structuredContent', $issued['result']);
        self::assertSame('elicitation/create', $issued['result']['inputRequests']->get('details')->get('method'));
        self::assertStringNotContainsString('private validation label', json_encode($issued));

        $queries->shouldReceive('fetch')->once()->withArgs(fn(SampleQuery $query): bool => $query->toArray() === ['value' => 'original'])->andReturn('safe public value');
        $second = new InteractiveValueTool($queries);
        $validator = $this->mock(Validator::class); // No method expectation: initial validation must not run again.
        $validation = new ValidationService();
        $validation->addValidator($validator);
        $params = $this->retry($issued['result']['requestState']);
        $params['arguments'] = ['nested' => ['a' => (object) [], 'b' => [true, null]], 'id' => 'original'];
        $resumed = $this->responder([$second], validation: $validation)->respond($this->wire($params, 2))->toArray();
        self::assertSame(0, $second->calls);
        self::assertSame(1, $second->resumes);
        self::assertEquals(StrictJson::fromData($arguments)->properties(), $second->input->toArray());
        self::assertSame('accepted', $second->responses->get('details')->content()->get('label'));
        self::assertSame('safe public value', $resumed['result']['structuredContent']->get('value'));
        self::assertSame(2, $resumed['id']);
        self::assertInstanceOf(NullMcpProgressReporter::class, $second->reporter);
    }

    #[DataProvider('ordinaryActions')]
    public function test_that_ordinary_actions_resume_once_and_only_accept_dispatches_a_mutation(string $action): void
    {
        $commands = $this->mock(CommandBus::class);
        $tool = new InteractiveValueTool($commands);
        $responder = $this->responder([$tool]);
        $token = $responder->respond($this->wire(['name' => 'value.interactive', 'arguments' => ['id' => 'original']]))->toArray()['result']['requestState'];
        if ($action === 'accept') {
            $commands->shouldReceive('execute')->once()->withArgs(fn(SampleCommand $command): bool => $command->toArray() === ['value' => 'original']);
        }
        $params = $this->retry($token);
        $params['inputResponses'] = ['details' => ['action' => $action]];
        if ($action === 'accept') {
            $params['inputResponses']['details']['content'] = ['label' => 'accepted'];
        }
        $result = $responder->respond($this->wire($params, 'fresh-id'))->toArray();
        self::assertSame('complete', $result['result']['resultType']);
        self::assertSame($action, $result['result']['structuredContent']->get('action'));
        self::assertSame(1, $tool->calls);
        self::assertSame(1, $tool->resumes);
        if ($action !== 'accept') {
            self::assertNull($tool->responses->get('details')->content());
        }
    }

    public static function ordinaryActions(): iterable
    {
        yield ['accept'];
        yield ['decline'];
        yield ['cancel'];
    }

    public function test_that_ordinary_replay_is_not_misrepresented_as_one_time_confirmation(): void
    {
        $tool = new InteractiveValueTool();
        $responder = $this->responder([$tool]);
        $token = $this->issue($responder);
        $first = $responder->respond($this->wire($this->retry($token), 2))->toArray();
        $second = $responder->respond($this->wire($this->retry($token), 3))->toArray();
        self::assertEquals($first['result'], $second['result']);
        self::assertSame(2, $tool->resumes);
    }

    #[DataProvider('incapableClients')]
    public function test_that_capability_gating_precedes_initial_schema_rules_and_handle(mixed $capabilities): void
    {
        $tool = new InteractiveValueTool();
        $result = $this->responder([$tool])->respond($this->wire(['name' => 'value.interactive', 'arguments' => null], capabilities: $capabilities))->toArray();
        self::assertSame(-32021, $result['error']['code']);
        self::assertSame('{"elicitation":{"form":{}}}', json_encode($result['error']['data']['requiredCapabilities']));
        self::assertSame(0, $tool->calls);
        self::assertSame(0, $tool->resumes);
    }

    public static function incapableClients(): iterable
    {
        yield [(object) []];
        yield [['elicitation' => (object) []]];
        yield [['elicitation' => ['url' => (object) []]]];
    }

    public function test_that_direct_invocation_without_request_capabilities_cannot_enter_an_interactive_tool(): void
    {
        $tool = new InteractiveValueTool();
        $invoker = new McpToolInvoker(new McpToolRegistry([$tool]), $this->available());
        try {
            $invoker->invoke('value.interactive', null);
            self::fail('Interactive tool entered without current capability evidence');
        } catch (McpProtocolException $error) {
            self::assertSame(-32021, $error->protocolError()->toArray()['code']);
            self::assertSame(0, $tool->calls);
        }
    }

    public function test_that_interactive_registration_requires_explicit_matching_metadata(): void
    {
        $tool = new class implements McpInteractiveTool {
            #[McpToolInfo('missing', 'Missing declaration', ['type' => 'object'], [])]
            public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput { throw new RuntimeException(); }
            public function resume(ApplicationData $input, McpInputResponses $responses, McpProgressReporter $progress): McpToolOutput { throw new RuntimeException(); }
        };
        $this->expectException(DomainException::class);
        new McpToolRegistry([$tool]);
    }

    public function test_that_capable_calls_require_explicit_caller_and_state_protection_composition(): void
    {
        $tool = new InteractiveValueTool();
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $responder = $this->responder([$tool], interaction: false, diagnostics: $diagnostics);
        $result = $responder->respond($this->wire(['name' => 'value.interactive', 'arguments' => ['id' => 'original']]))->toArray();
        self::assertSame(-32603, $result['error']['code']);
        self::assertSame(0, $tool->calls);
        $retry = $responder->respond($this->wire($this->retry('unusable'), 2))->toArray();
        self::assertSame(-32602, $retry['error']['code']);
    }

    public function test_that_revocation_conceals_the_tool_before_binding_or_response_validation(): void
    {
        $tool = new InteractiveValueTool();
        $token = $this->issue($this->responder([$tool]));
        $availability = $this->available(false);
        $params = $this->retry($token);
        $params['arguments'] = 'replaced';
        $params['inputResponses'] = 'malformed';
        $hidden = $this->responder([$tool], availability: $availability, interaction: new McpToolInteraction($this->protector(), 'other-caller'))
            ->respond($this->wire($params, 2))->toArray();
        $unknown = $this->responder([])->respond($this->wire($params, 2))->toArray();
        self::assertSame($unknown, $hidden);
        self::assertSame('Unknown or unavailable tool.', $hidden['error']['message']);
        self::assertSame(0, $tool->resumes);
    }

    public function test_that_retry_requires_current_capability_evidence_not_the_initial_request(): void
    {
        $tool = new InteractiveValueTool();
        $responder = $this->responder([$tool]);
        $token = $this->issue($responder);
        $result = $responder->respond($this->wire($this->retry($token), 2, (object) []))->toArray();
        self::assertSame(-32021, $result['error']['code']);
        self::assertSame(0, $tool->resumes);
    }

    #[DataProvider('badRetries')]
    public function test_that_invalid_retries_are_indistinguishable_and_never_resume(string $change): void
    {
        $tool = new InteractiveValueTool();
        $responder = $this->responder([$tool]);
        $params = $this->retry($this->issue($responder));
        $id = 2;
        switch ($change) {
            case 'same-id': $id = 1; break;
            case 'arguments': $params['arguments']['id'] = 'replacement'; break;
            case 'argument-types': $params['arguments'] = []; break;
            case 'name': $params['name'] = 'different'; break;
            case 'missing-state': unset($params['requestState']); break;
            case 'missing-responses': unset($params['inputResponses']); break;
            case 'oversized': $params['requestState'] = str_repeat('a', 65537); break;
            case 'state-type': $params['requestState'] = []; break;
            case 'tampered': $params['requestState'] .= 'x'; break;
            case 'retired': $params['requestState'] = new SodiumMcpStateProtector(2, [2 => str_repeat('z', 32)])->seal('state'); break;
            case 'response-shape': $params['inputResponses'] = []; break;
            case 'response-key': $params['inputResponses'] = ['extra' => ['action' => 'cancel']]; break;
            case 'schema': $params['inputResponses']['details']['content']['label'] = 42; break;
            case 'rules': $params['inputResponses']['details']['content']['label'] = '  '; break;
            case 'caller': $responder = $this->responder([$tool], interaction: new McpToolInteraction($this->protector(), 'other-caller')); break;
            default:
                $state = StrictJson::fromString($this->protector()->open($params['requestState']), 70);
                $state = match ($change) {
                    'expired' => $state->with('expires', time() - 1, 70),
                    'mode' => $state->with('mode', 'confirmation', 70),
                    'method' => $state->with('method', 'other', 70),
                    'malformed-validated' => $state->with('validated', [], 70),
                    'malformed-contracts' => $state->with('contracts', [], 70),
                    'bad-contract' => $state->with('contracts', ['details' => ['message' => 'broken']], 70),
                };
                $params['requestState'] = $this->protector()->seal($state->toString());
        }
        $result = $responder->respond($this->wire($params, $id))->toArray();
        self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $result['error']);
        self::assertSame(0, $tool->resumes);
    }

    public static function badRetries(): iterable
    {
        foreach (['same-id', 'arguments', 'argument-types', 'name', 'missing-state', 'missing-responses', 'oversized', 'state-type', 'tampered', 'retired', 'response-shape', 'response-key', 'schema', 'rules', 'caller', 'expired', 'mode', 'method', 'malformed-validated', 'malformed-contracts', 'bad-contract'] as $change) {
            yield $change => [$change];
        }
    }

    public function test_that_byte_limit_is_enforced_before_the_cryptographic_boundary(): void
    {
        $protector = $this->mock(McpStateProtector::class); // No open expectation.
        $interaction = new McpToolInteraction($protector, 'caller');
        $this->expectException(McpProtocolException::class);
        $interaction->open(str_repeat('x', 65537));
    }

    #[DataProvider('invalidOpenedState')]
    public function test_that_opened_state_must_identify_a_tool_before_selection(string $plaintext): void
    {
        $interaction = new McpToolInteraction($this->protector(), 'caller');
        $this->expectException(McpProtocolException::class);
        $interaction->open($this->protector()->seal($plaintext));
    }

    public static function invalidOpenedState(): iterable
    {
        yield ['not json'];
        yield ['[]'];
        yield ['{}'];
        yield ['{"tool":42}'];
    }

    public function test_that_a_token_for_a_noninteractive_tool_is_not_used_for_fresh_execution(): void
    {
        $token = $this->protector()->seal('{"tool":"echo"}');
        $tool = new EchoTool();
        $params = $this->retry($token);
        $params['name'] = 'echo';
        $result = $this->responder([$tool])->respond($this->wire($params, 2))->toArray();
        self::assertSame(-32602, $result['error']['code']);
        self::assertSame(0, $tool->calls);
    }

    public function test_that_safe_resume_failures_use_the_existing_mapping_and_unexpected_failures_are_diagnosed_once(): void
    {
        $tool = new InteractiveValueTool();
        $token = $this->issue($this->responder([$tool]));
        $tool->failure = new RuntimeException('credential secret');
        $mapped = $this->responder([$tool], failures: new McpToolFailureMap([RuntimeException::class => 'Safe outcome']))
            ->respond($this->wire($this->retry($token), 2))->toArray();
        self::assertTrue($mapped['result']['isError']);
        self::assertSame('Safe outcome', $mapped['result']['content'][0]['text']);
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $unexpected = $this->responder([$tool], diagnostics: $diagnostics)->respond($this->wire($this->retry($token), 3))->toArray();
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $unexpected['error']);
    }

    public function test_that_an_interactive_tool_can_complete_without_requesting_input(): void
    {
        $tool = new InteractiveValueTool();
        $tool->output = McpToolOutput::structured(['complete' => true]);
        $result = $this->responder([$tool])->respond($this->wire(['name' => 'value.interactive', 'arguments' => ['id' => 'original']]))->toArray();
        self::assertSame('complete', $result['result']['resultType']);
        self::assertSame(1, $tool->calls);
    }

    public function test_that_a_later_round_issues_a_new_token_without_reentering_initial_handle(): void
    {
        $tool = new InteractiveValueTool();
        $responder = $this->responder([$tool]);
        $first = $this->issue($responder);
        $tool->askAgain = true;
        $next = $responder->respond($this->wire($this->retry($first), 2))->toArray();
        self::assertSame('input_required', $next['result']['resultType']);
        self::assertNotSame($first, $next['result']['requestState']);
        $tool->askAgain = false;
        $last = $responder->respond($this->wire($this->retry($next['result']['requestState']), 3))->toArray();
        self::assertSame('complete', $last['result']['resultType']);
        self::assertSame(1, $tool->calls);
        self::assertSame(2, $tool->resumes);
    }

    public function test_that_noninteractive_tools_cannot_emit_input_requests_through_the_widened_base_contract(): void
    {
        $tool = new class implements McpTool {
            #[McpToolInfo('ordinary', 'Ordinary', ['type' => 'object'], [])]
            public function handle(ApplicationData $input, McpProgressReporter $progress): McpInputRequired
            {
                return McpInputRequired::fromRequests(['input' => McpInputRequest::form('Input', ['type' => 'object', 'properties' => []])]);
            }
        };
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $result = $this->responder([$tool], diagnostics: $diagnostics)->respond($this->wire(['name' => 'ordinary']))->toArray();
        self::assertSame(-32603, $result['error']['code']);
    }

    #[DataProvider('badConfiguration')]
    public function test_that_caller_and_expiry_configuration_are_bounded(string $caller, int $ttl): void
    {
        $this->expectException(DomainException::class);
        new McpToolInteraction($this->protector(), $caller, $ttl);
    }

    public static function badConfiguration(): iterable
    {
        yield ['', 300];
        yield [str_repeat('x', 1025), 300];
        yield ['caller', 0];
        yield ['caller', 901];
    }

    public function test_that_direct_semantic_invocation_rejects_an_unrelated_method(): void
    {
        $invoker = new McpToolInvoker(new McpToolRegistry([]), $this->available());
        $request = new McpRequestDecoder()->decode($this->wire([]));
        $invalid = new McpRequest($request->id(), 'other', [], $request->metadata());
        $this->expectException(McpProtocolException::class);
        $invoker->invokeRequest($invalid);
    }

    public function test_that_initial_state_overflow_is_sanitized_without_dispatch(): void
    {
        $queries = $this->mock(QueryBus::class);
        $tool = new InteractiveValueTool($queries);
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $result = $this->responder([$tool], diagnostics: $diagnostics)->respond($this->wire([
            'name' => 'value.interactive', 'arguments' => ['id' => str_repeat('x', 50000)]
        ]))->toArray();
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $result['error']);
        self::assertSame(1, $tool->calls);
        self::assertSame(0, $tool->resumes);
    }

    public function test_that_original_scalar_types_and_list_order_are_integrity_bound(): void
    {
        $tool = new InteractiveValueTool();
        $responder = $this->responder([$tool]);
        $arguments = ['id' => 'original', 'number' => 1.0, 'list' => ['first', 'second']];
        $initial = $responder->respond($this->wire(['name' => 'value.interactive', 'arguments' => $arguments]))->toArray();
        $params = $this->retry($initial['result']['requestState']);
        foreach ([['number' => 1], ['list' => ['second', 'first']], ['list' => (object) ['0' => 'first', '1' => 'second']]] as $change) {
            $params['arguments'] = array_replace($arguments, $change);
            $result = $responder->respond($this->wire($params, 2))->toArray();
            self::assertSame(-32602, $result['error']['code']);
        }
        self::assertSame(0, $tool->resumes);
    }

    public function test_that_resume_receives_the_current_reporter_not_the_initial_reporter(): void
    {
        $tool = new InteractiveValueTool();
        $token = $this->issue($this->responder([$tool]));
        $initial = $tool->reporter;
        $current = $this->mock(McpProgressReporter::class);
        $invoker = new McpToolInvoker(new McpToolRegistry([$tool]), $this->available(), interaction: new McpToolInteraction($this->protector(), 'caller'));
        $result = $invoker->invokeRequest(new McpRequestDecoder()->decode($this->wire($this->retry($token), 2)), $current);
        self::assertSame('complete', $result->toArray()['resultType']);
        self::assertNotSame($initial, $tool->reporter);
        self::assertSame($current, $tool->reporter);
    }

    private function available(bool $allowed = true): McpToolAvailability
    {
        return new class($allowed) implements McpToolAvailability {
            public function __construct(private bool $allowed) {}
            public function isAvailable(McpToolInfo $tool): bool { return $this->allowed; }
        };
    }

    private function protector(): SodiumMcpStateProtector
    {
        return new SodiumMcpStateProtector(1, [1 => str_repeat('k', 32)]);
    }

    private function responder(array $tools, ?McpToolAvailability $availability = null, McpToolInteraction|false|null $interaction = null, ?ValidationService $validation = null, ?McpDiagnostics $diagnostics = null, ?McpToolFailureMap $failures = null): McpResponder
    {
        $registry = new McpToolRegistry($tools);
        $availability ??= $this->available();
        $interaction ??= new McpToolInteraction($this->protector(), 'caller');
        $invoker = new McpToolInvoker($registry, $availability, $validation ?? new ValidationService(), $failures ?? new McpToolFailureMap(), interaction: $interaction === false ? null : $interaction);
        return new McpResponder(new McpCapabilityRegistry(new McpServerInfo('test', '1'), [
            new McpToolDiscovery($registry, $availability, str_repeat('c', 32)),
            new McpToolInvocation($invoker)
        ]), diagnostics: $diagnostics ?? $this->mock(McpDiagnostics::class));
    }

    private function issue(McpResponder $responder): string
    {
        return $responder->respond($this->wire(['name' => 'value.interactive', 'arguments' => ['id' => 'original']]))->toArray()['result']['requestState'];
    }

    private function retry(string $token): array
    {
        return ['name' => 'value.interactive', 'arguments' => ['id' => 'original'], 'requestState' => $token, 'inputResponses' => ['details' => ['action' => 'accept', 'content' => ['label' => 'accepted']]]];
    }

    private function wire(array $parameters, int|string $id = 1, mixed $capabilities = null): string
    {
        $parameters['_meta'] = [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => $capabilities ?? ['elicitation' => ['form' => (object) []]]
        ];
        return json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => $parameters], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }
}
