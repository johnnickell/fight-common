<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolFailureMap;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Mcp\Tool\Messaging\McpToolAuditMetadata;
use Fight\Common\Application\Mcp\Tool\Messaging\McpToolMetadataFilter;
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
use Fight\Test\Common\Fixture\Mcp\ReadValueTool;
use Fight\Test\Common\Fixture\Mcp\WriteValueTool;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Throwable;

#[CoversClass(McpToolInvoker::class)]
#[CoversClass(McpToolInvocation::class)]
#[CoversClass(McpToolFailureMap::class)]
#[CoversClass(McpProtocolError::class)]
#[CoversClass(McpResponder::class)]
final class McpToolInvokerTest extends UnitTestCase
{
    public function test_that_exact_arguments_with_empty_rules_reach_the_selected_tool_once(): void
    {
        $tool = new EchoTool();
        $availability = new InvocationAvailability();
        $responder = $this->responder([$tool], $availability);
        $arguments = (object) ['name' => 'argument-not-tool', 'id' => 999, 'nested' => (object) ['a' => []], 'empty' => new stdClass()];
        $wire = $responder->respond($this->wire(['name' => 'echo', 'arguments' => $arguments]))->toArray();
        self::assertSame(1, $tool->calls);
        self::assertSame(['echo'], $availability->seen);
        self::assertEquals(StrictJson::fromData($arguments)->properties(), $tool->input->toArray());
        self::assertInstanceOf(NullMcpProgressReporter::class, $tool->reporter);
        self::assertFalse($tool->reporter->isCancelled());
        self::assertEquals(StrictJson::fromData($arguments), $wire['result']['structuredContent']);
        self::assertSame(json_encode($arguments), $wire['result']['content'][0]['text']);
        self::assertArrayNotHasKey('isError', $wire['result']);
        self::assertSame('complete', $wire['result']['resultType']);
        self::assertArrayNotHasKey('_meta', $tool->input->toArray());
    }

    public function test_that_protocol_wrappers_do_not_consume_the_tool_argument_depth_budget(): void
    {
        $responder = $this->responder([new EchoTool()]);
        $nested = null;
        for ($level = 0; $level < 63; ++$level) {
            $nested = [$nested];
        }
        $allowed = $responder->respond($this->wire(['name' => 'echo', 'arguments' => ['nested' => $nested]]))->toArray();
        self::assertArrayNotHasKey('error', $allowed);
        self::assertArrayNotHasKey('isError', $allowed['result']);
        self::assertSame(json_encode(['nested' => $nested]), $allowed['result']['content'][0]['text']);
        $rejected = $responder->respond($this->wire(['name' => 'echo', 'arguments' => ['nested' => [$nested]]]))->toArray();
        self::assertArrayNotHasKey('error', $rejected);
        self::assertTrue($rejected['result']['isError']);
        self::assertSame('Tool arguments must contain supported JSON values.', $rejected['result']['content'][0]['text']);
    }

    public function test_that_omitted_arguments_and_missing_validation_attribute_are_supported(): void
    {
        $tool = new class implements McpTool {
            public ?ApplicationData $input = null;
            #[McpToolInfo('no.rules', 'No rules', ['type' => 'object'], [])]
            public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
            {
                $this->input = $input;
                return McpToolOutput::structured(null);
            }
        };
        $result = $this->responder([$tool])->respond($this->wire(['name' => 'no.rules']))->toArray()['result'];
        self::assertSame([], $tool->input->toArray());
        self::assertNull($result['structuredContent']);
        self::assertSame('null', $result['content'][0]['text']);
    }

    public function test_that_query_tool_maps_one_query_and_projects_only_safe_output(): void
    {
        $queries = $this->mock(QueryBus::class);
        $queries->shouldReceive('fetch')->once()->withArgs(fn(SampleQuery $query): bool => $query->toArray() === ['value' => 'item-1'])
            ->andReturn(['value' => 'public', 'secret' => 'must not leave the consumer']);
        $result = $this->responder([new ReadValueTool($queries)])
            ->respond($this->wire(['name' => 'value.read', 'arguments' => (object) ['id' => 'item-1']]))->toArray()['result'];
        self::assertEquals(StrictJson::fromObject(['value' => 'public']), $result['structuredContent']);
        self::assertSame('{"value":"public"}', $result['content'][0]['text']);
        self::assertStringNotContainsString('secret', json_encode($result));
    }

    public function test_that_mutation_tool_executes_once_and_acknowledges_only_the_known_identifier(): void
    {
        $commands = $this->mock(CommandBus::class);
        $commands->shouldReceive('execute')->once()->withArgs(fn(SampleCommand $command): bool => $command->toArray() === ['value' => 'caller-id']);
        $result = $this->responder([new WriteValueTool($commands)])
            ->respond($this->wire(['name' => 'value.write', 'arguments' => (object) ['id' => 'caller-id']]))->toArray()['result'];
        self::assertEquals(StrictJson::fromObject(['id' => 'caller-id']), $result['structuredContent']);
        self::assertSame('{"id":"caller-id"}', $result['content'][0]['text']);
    }

    #[DataProvider('invalidArguments')]
    public function test_that_schema_and_rule_failures_are_tool_results_without_bus_dispatch(mixed $arguments, string $message): void
    {
        $queries = $this->mock(QueryBus::class);
        $result = $this->responder([new ReadValueTool($queries)])
            ->respond($this->wire(['name' => 'value.read', 'arguments' => $arguments]))->toArray();
        self::assertSame(true, $result['result']['isError']);
        self::assertSame($message, $result['result']['content'][0]['text']);
        self::assertArrayNotHasKey('structuredContent', $result['result']);
        self::assertArrayNotHasKey('error', $result);
    }

    public static function invalidArguments(): iterable
    {
        yield 'null' => [null, 'Tool arguments must be an object.'];
        yield 'list' => [[], 'Tool arguments must be an object.'];
        yield 'missing required' => [(object) [], 'Tool arguments do not match the input schema.'];
        yield 'wrong type' => [(object) ['id' => 1], 'Tool arguments do not match the input schema.'];
        yield 'too short' => [(object) ['id' => ''], 'Tool arguments do not match the input schema.'];
        yield 'additional property' => [(object) ['id' => 'x', 'secret' => 'no'], 'Tool arguments do not match the input schema.'];
        yield 'runtime rule' => [(object) ['id' => '  '], 'Tool argument validation failed.'];
    }

    public function test_that_unknown_and_unavailable_tools_are_identical_before_arguments_rules_or_metadata(): void
    {
        $tool = new EchoTool();
        $availability = new InvocationAvailability();
        $availability->available = false;
        $audit = $this->mock(McpToolAuditMetadata::class);
        $diagnostics = $this->mock(McpDiagnostics::class);
        $responder = $this->responder([$tool], $availability, diagnostics: $diagnostics, metadata: new McpToolMetadataFilter($audit));
        $unknown = $responder->respond($this->wire(['name' => 'absent', 'arguments' => 'secret invalid arguments']))->toArray();
        $hidden = $responder->respond($this->wire(['name' => 'echo', 'arguments' => 'secret invalid arguments']))->toArray();
        self::assertSame($unknown, $hidden);
        self::assertSame(['code' => -32602, 'message' => 'Unknown or unavailable tool.'], $hidden['error']);
        self::assertSame(['echo'], $availability->seen);
        self::assertSame(0, $tool->calls);
        self::assertNull($tool->input);
        $list = $responder->respond($this->wire([], 'tools/list'))->toArray();
        self::assertSame([], $list['result']['tools']);
    }

    public function test_that_only_exact_registered_business_failures_expose_authored_text(): void
    {
        $tool = new EchoTool();
        $tool->failure = new RuntimeException('credential secret /internal/path');
        $diagnostics = $this->mock(McpDiagnostics::class);
        $result = $this->responder([$tool], failures: new McpToolFailureMap([RuntimeException::class => 'The item is unavailable.']), diagnostics: $diagnostics)
            ->respond($this->wire(['name' => 'echo']))->toArray();
        self::assertSame(true, $result['result']['isError']);
        self::assertSame('The item is unavailable.', $result['result']['content'][0]['text']);
        self::assertStringNotContainsString('credential', json_encode($result));
        self::assertNull((new McpToolFailureMap([RuntimeException::class => 'Safe']))->messageFor(new DomainException('private')));
    }

    public function test_that_unexpected_and_tool_protocol_failures_are_logged_once_and_never_exposed(): void
    {
        foreach ([new RuntimeException('credential'), new McpProtocolException(McpProtocolError::headerMismatch(), 'private-id')] as $failure) {
            $tool = new EchoTool();
            $tool->failure = $failure;
            $diagnostics = $this->mock(McpDiagnostics::class);
            $diagnostics->shouldReceive('record')->once()->withArgs(function (Throwable $recorded) use ($failure): bool {
                while ($recorded->getPrevious() !== null) {
                    $recorded = $recorded->getPrevious();
                }
                return $recorded === $failure;
            });
            $result = $this->responder([$tool], diagnostics: $diagnostics)->respond($this->wire(['name' => 'echo']))->toArray();
            self::assertSame(['jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32603, 'message' => 'Internal error.']], $result);
        }
    }

    public function test_that_broken_diagnostics_do_not_expose_another_failure(): void
    {
        $tool = new EchoTool();
        $tool->failure = new RuntimeException('private');
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once()->andThrow(new RuntimeException('sink credential'));
        $result = $this->responder([$tool], diagnostics: $diagnostics)->respond($this->wire(['name' => 'echo']))->toArray();
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $result['error']);
    }

    public function test_that_dispatch_leaves_diagnostics_to_the_transport_boundary(): void
    {
        $tool = new EchoTool();
        $tool->failure = new RuntimeException('private');
        $diagnostics = $this->mock(McpDiagnostics::class);
        $responder = $this->responder([$tool], diagnostics: $diagnostics);
        $this->expectException(DomainException::class);
        $responder->dispatch(new McpRequestDecoder()->decode($this->wire(['name' => 'echo'])));
    }

    public function test_that_availability_failure_is_not_a_public_tool_failure(): void
    {
        $availability = new InvocationAvailability();
        $availability->failure = new McpProtocolException(McpProtocolError::headerMismatch(), 99);
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $tool = new EchoTool();
        $result = $this->responder([$tool], $availability, new McpToolFailureMap([McpProtocolException::class => 'Not public here']), $diagnostics)
            ->respond($this->wire(['name' => 'echo']))->toArray();
        self::assertSame(-32603, $result['error']['code']);
        self::assertSame(0, $tool->calls);
    }

    public function test_that_nonconformant_output_is_not_mapped_to_a_public_business_failure(): void
    {
        $queries = $this->mock(QueryBus::class);
        $queries->shouldReceive('fetch')->once()->andReturn(['value' => 1]);
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $result = $this->responder([new ReadValueTool($queries)], failures: new McpToolFailureMap([DomainException::class => 'Not public here']), diagnostics: $diagnostics)
            ->respond($this->wire(['name' => 'value.read', 'arguments' => (object) ['id' => 'item-1']]))->toArray();
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $result['error']);
        self::assertArrayNotHasKey('result', $result);
    }

    public function test_that_plain_json_boundaries_reject_serializers_depth_and_numeric_argument_names(): void
    {
        $tool = new EchoTool();
        $invoker = new McpToolInvoker(new McpToolRegistry([$tool]), new InvocationAvailability());
        $nested = (object) [];
        for ($i = 0; $i < 65; ++$i) {
            $nested = (object) ['nested' => $nested];
        }
        foreach ([(object) ['0' => 'x'], (object) ['value' => new RuntimeException('private')], $nested] as $arguments) {
            self::assertTrue($invoker->invoke('echo', $arguments)->toArray()['isError']);
        }
        self::assertSame(0, $tool->calls);
    }

    public function test_that_unexpected_validation_and_metadata_failures_cannot_impersonate_protocol_errors(): void
    {
        $validation = new ValidationService();
        $validator = $this->mock(Validator::class);
        $validator->shouldReceive('validate')->once()->withArgs(fn(ValidationContext $context): bool => true)
            ->andThrow(new McpProtocolException(McpProtocolError::headerMismatch(), 'private'));
        $validation->addValidator($validator);
        $tool = new EchoTool();
        $registry = new McpToolRegistry([$tool]);
        $invoker = new McpToolInvoker($registry, new InvocationAvailability(), $validation);
        try {
            $invoker->invoke('echo', (object) []);
            self::fail('Unexpected validation failure must propagate to diagnostics.');
        } catch (DomainException $failure) {
            self::assertInstanceOf(McpProtocolException::class, $failure->getPrevious());
        }
        $audit = $this->mock(McpToolAuditMetadata::class);
        $audit->shouldReceive('fieldsFor')->once()->andThrow(new McpProtocolException(McpProtocolError::headerMismatch(), 'private'));
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->once();
        $responder = $this->responder([$tool], failures: new McpToolFailureMap([McpProtocolException::class => 'Not public here']), diagnostics: $diagnostics, metadata: new McpToolMetadataFilter($audit));
        self::assertSame(-32603, $responder->respond($this->wire(['name' => 'echo']))->errorCode());
        self::assertSame(0, $tool->calls);
    }

    #[DataProvider('invalidOuterParameters')]
    public function test_that_invalid_outer_parameters_remain_protocol_errors(array $parameters, string $method): void
    {
        $capability = new McpToolInvocation(new McpToolInvoker(new McpToolRegistry([]), new InvocationAvailability()));
        self::assertSame(['tools/call'], $capability->methods());
        self::assertSame([], $capability->mirrorDeclarations());
        $this->expectException(McpProtocolException::class);
        $capability->handle(new McpRequestDecoder()->decode($this->wire($parameters, $method)));
    }

    public static function invalidOuterParameters(): iterable
    {
        yield [[], 'tools/call'];
        yield [['name' => null], 'tools/call'];
        yield [['name' => 1], 'tools/call'];
        yield [['name' => 'echo', 'unexpected' => 1], 'tools/call'];
        yield [['name' => 'echo'], 'tools/list'];
    }

    #[DataProvider('invalidFailureMappings')]
    public function test_that_invalid_failure_mappings_fail_composition(array $mapping): void
    {
        $this->expectException(DomainException::class);
        new McpToolFailureMap($mapping);
    }

    public static function invalidFailureMappings(): iterable
    {
        yield [[stdClass::class => 'Message']];
        yield [[RuntimeException::class => '  ']];
        yield [[RuntimeException::class => str_repeat('a', 4097)]];
        yield [[RuntimeException::class => "private\ntext"]];
        yield [[RuntimeException::class => "\xff"]];
    }

    private function responder(array $tools, ?InvocationAvailability $availability = null, ?McpToolFailureMap $failures = null, ?McpDiagnostics $diagnostics = null, ?McpToolMetadataFilter $metadata = null): McpResponder
    {
        $registry = new McpToolRegistry($tools);
        $availability ??= new InvocationAvailability();
        return new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [
            new McpToolDiscovery($registry, $availability, str_repeat('x', 32)),
            new McpToolInvocation(new McpToolInvoker($registry, $availability, failures: $failures ?? new McpToolFailureMap(), metadata: $metadata)),
        ]), diagnostics: $diagnostics);
    }

    private function wire(array $parameters, string $method = 'tools/call'): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => [...$parameters, '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => new stdClass(),
        ]]], JSON_THROW_ON_ERROR);
    }
}

final class InvocationAvailability implements McpToolAvailability
{
    public array $seen = [];
    public bool $available = true;
    public ?Throwable $failure = null;

    public function isAvailable(McpToolInfo $tool): bool
    {
        $this->seen[] = $tool->name();
        if ($this->failure !== null) {
            throw $this->failure;
        }
        return $this->available;
    }
}
