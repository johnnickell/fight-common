<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Prompt;

use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Prompt\McpPromptAvailability;
use Fight\Common\Application\Mcp\Prompt\McpPromptDiscovery;
use Fight\Common\Application\Mcp\Prompt\McpPromptInfo;
use Fight\Common\Application\Mcp\Prompt\McpPromptLimits;
use Fight\Common\Application\Mcp\Prompt\McpPromptOutput;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\Fixture\Mcp\PromptProvider;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

#[CoversClass(McpPromptDiscovery::class)]
final class McpPromptDiscoveryTest extends UnitTestCase
{
    private const string KEY = 'fixture-only-prompt-cursor-key-00157';

    public function test_that_registration_is_lazy_and_listing_filters_before_deterministic_stateless_pagination(): void
    {
        $policy = new PromptAvailability();
        $providers = $this->providers();
        $capability = $this->capability($providers, $policy);
        self::assertSame(['prompts/list', 'prompts/get'], $capability->methods());
        self::assertSame(['prompts' => ['listChanged' => false]], $capability->capabilities());
        self::assertSame([], $capability->mirrorDeclarations());
        foreach ($providers as $provider) {
            self::assertSame(1, $provider->metadataCalls);
            self::assertSame([], $provider->calls);
        }
        $first = $capability->handle($this->request())->toArray();
        self::assertSame(['Alpha'], array_column($first['prompts'], 'name'));
        self::assertSame(0, $first['ttlMs']);
        self::assertSame('private', $first['cacheScope']);
        self::assertSame(48, strlen($first['nextCursor']));
        self::assertStringNotContainsString('Hidden', json_encode($first));
        self::assertSame(['Alpha', 'Hidden', 'Zulu'], $policy->seen);
        // New process composition, same visible catalog: no remembered connection history or hidden hash.
        $fresh = $this->capability([$providers[0], $providers[2]], new PromptAvailability());
        self::assertEquals($first, $fresh->handle($this->request(['cursor' => '']))->toArray());
        $last = $fresh->handle($this->request(['cursor' => $first['nextCursor']]))->toArray();
        self::assertSame(['Zulu'], array_column($last['prompts'], 'name'));
        self::assertArrayNotHasKey('nextCursor', $last);
        foreach ($providers as $provider) {
            self::assertSame([], $provider->calls);
        }
        $empty = $this->capability([], new PromptAvailability())->handle($this->request())->toArray();
        self::assertSame([], $empty['prompts']);
        self::assertArrayNotHasKey('nextCursor', $empty);
    }

    public function test_that_only_selected_currently_permitted_provider_receives_declared_arguments_without_prior_listing(): void
    {
        $providers = $this->providers();
        $policy = new PromptAvailability();
        $capability = $this->capability($providers, $policy);
        $result = $capability->handle($this->request(['name' => 'Alpha', 'arguments' => (object) ['id' => '', 'optional' => '雪']], 'prompts/get'))->toArray();
        self::assertSame([], $result['messages']);
        self::assertSame('complete', $result['resultType']);
        self::assertSame([['id' => '', 'optional' => '雪']], $providers[2]->calls);
        self::assertSame([], $providers[0]->calls);
        self::assertSame([], $providers[1]->calls);
        $capability->handle($this->request(['name' => 'Zulu'], 'prompts/get'));
        self::assertSame([[]], $providers[0]->calls);
        $capability->handle($this->request());
        $policy->hidden = 'Alpha';
        foreach (['Alpha', 'Unknown'] as $name) {
            self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $this->respond($capability, ['name' => $name, 'arguments' => (object) ['id' => 'forged-admin']], 'prompts/get')['error']);
        }
        self::assertCount(1, $providers[2]->calls);
    }

    #[DataProvider('invalidParameters')]
    public function test_that_invalid_outer_shape_rejects_before_policy_or_processing(string $method, array $params): void
    {
        $policy = new PromptAvailability();
        $providers = $this->providers();
        try {
            $this->capability($providers, $policy)->handle($this->request($params, $method));
            self::fail('Invalid shape accepted');
        } catch (McpProtocolException $exception) {
            self::assertSame(-32602, $exception->protocolError()->toArray()['code']);
            self::assertSame(7, $exception->requestId());
        }
        self::assertSame([], $policy->seen);
        self::assertSame([], $providers[2]->calls);
    }

    public static function invalidParameters(): iterable
    {
        foreach ([['extra' => true], ['cursor' => null], ['cursor' => 1], ['cursor' => str_repeat('!', 48)], ['cursor' => str_repeat('a', 49)]] as $params) { yield ['prompts/list', $params]; }
        foreach ([[], ['name' => null], ['name' => 7], ['name' => str_repeat('a', 129)], ['name' => 'Alpha', 'extra' => 1], ['name' => 'Alpha', 'arguments' => null], ['name' => 'Alpha', 'arguments' => []], ['name' => 'Alpha', 'arguments' => 'text']] as $params) { yield ['prompts/get', $params]; }
        yield ['other', []];
    }

    public function test_that_declared_argument_failures_precede_provider_processing_and_disclose_no_declarations(): void
    {
        $providers = $this->providers();
        $capability = $this->capability($providers, new PromptAvailability());
        foreach ([null, (object) [], (object) ['id' => 1], (object) ['id' => null], (object) ['id' => 'x', 'principal' => 'admin'], (object) ['id' => str_repeat('x', 16385)]] as $arguments) {
            $params = ['name' => 'Alpha'];
            if ($arguments !== null) { $params['arguments'] = $arguments; }
            self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $this->respond($capability, $params, 'prompts/get')['error']);
        }
        self::assertSame([], $providers[2]->calls);
    }

    public function test_that_tampered_stale_foreign_and_out_of_range_cursors_reject_without_partial_pages(): void
    {
        $providers = $this->providers();
        $policy = new PromptAvailability();
        $capability = $this->capability($providers, $policy);
        $cursor = $capability->handle($this->request())->toArray()['nextCursor'];
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        foreach ([0, 2, 100] as $offset) {
            $forged = strtr(base64_encode(pack('N', $offset).substr($raw, 4)), '+/', '-_');
            self::assertSame(-32602, $this->respond($capability, ['cursor' => $forged])['error']['code']);
        }
        $tampered = strtr(base64_encode(pack('N', 1).str_repeat('x', 32)), '+/', '-_');
        self::assertSame(-32602, $this->respond($capability, ['cursor' => $tampered])['error']['code']);
        foreach ([
            $this->capability($providers, $policy, scope: 'other'),
            $this->capability($providers, $policy, key: str_repeat('k', 32)),
            $this->capability($providers, $policy, limits: new McpPromptLimits(pageSize: 2)),
            $this->capability($providers, $policy, limits: new McpPromptLimits(pageSize: 1, maxResponseBytes: 999999)),
            $this->capability([$providers[2]], $policy),
            $this->capability([new PromptProvider(McpPromptInfo::fromArray(['name' => 'Alpha', 'description' => 'changed'])), $providers[0]], $policy),
        ] as $other) {
            self::assertSame(-32602, $this->respond($other, ['cursor' => $cursor])['error']['code']);
        }
        $policy->hidden = 'Alpha';
        self::assertSame(-32602, $this->respond($capability, ['cursor' => $cursor])['error']['code']);
        self::assertSame(['Hidden'], array_column($capability->handle($this->request())->toArray()['prompts'], 'name'));
        // A correctly signed but non-page-aligned offset is invalid too.
        $two = $this->capability($providers, new PromptAvailability(''), limits: new McpPromptLimits(pageSize: 2));
        self::assertSame(-32602, $this->respond($two, ['cursor' => $cursor])['error']['code']);
    }

    public function test_that_provider_and_policy_failures_remain_sanitized_diagnosed_internal_errors_not_empty_success(): void
    {
        $providers = $this->providers();
        $policy = new PromptAvailability();
        $diagnostics = new class implements McpDiagnostics {
            public int $calls = 0;
            public function record(Throwable $failure): void { ++$this->calls; }
        };
        $capability = $this->capability($providers, $policy);
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [$capability]), diagnostics: $diagnostics);
        foreach ([new RuntimeException('private credential'), new McpProtocolException(McpProtocolError::invalidRequest(), 7)] as $failure) {
            $providers[0]->failure = $failure;
            self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $responder->respond($this->wire(['name' => 'Zulu'], 'prompts/get'))->toArray()['error']);
            $policy->failure = $failure;
            foreach (['prompts/list' => [], 'prompts/get' => ['name' => 'Zulu']] as $method => $params) {
                self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $responder->respond($this->wire($params, $method))->toArray()['error']);
            }
            $policy->failure = null;
        }
        self::assertSame(6, $diagnostics->calls);
    }

    public function test_that_actual_central_metadata_and_envelope_escaping_are_in_exact_independent_serving_budgets(): void
    {
        $provider = new PromptProvider(McpPromptInfo::fromArray(['name' => 'Prompt', 'description' => '雪"\\']));
        $provider->output = McpPromptOutput::fromArray(['messages' => [['role' => 'assistant', 'content' => ['type' => 'text', 'text' => "雪\n\"\\"]]]]);
        foreach (['prompts/list' => [], 'prompts/get' => ['name' => 'Prompt']] as $method => $params) {
            $info = new McpServerInfo('Identity雪"', str_repeat('v', 200));
            $providers = [$provider, new PromptProvider(McpPromptInfo::fromArray(['name' => 'Zulu']))];
            $normal = $this->capability($providers, new PromptAvailability());
            $responder = new McpResponder(new McpCapabilityRegistry($info, [$normal]));
            $wire = $this->wire($params, $method, str_repeat('"', 40));
            $response = $responder->respond($wire);
            $resultBytes = strlen(json_encode($response->toArray()['result']));
            $responseBytes = strlen($response->toJson());
            self::assertGreaterThan($resultBytes, $responseBytes);
            foreach ([[$resultBytes, $responseBytes, true], [$resultBytes - 1, $responseBytes, false], [$resultBytes, $responseBytes - 1, false]] as [$resultLimit, $responseLimit, $success]) {
                $limited = $this->capability($providers, new PromptAvailability(), limits: new McpPromptLimits(pageSize: 1, maxResultBytes: $resultLimit, maxResponseBytes: $responseLimit));
                $actual = (new McpResponder(new McpCapabilityRegistry($info, [$limited])))->respond($wire)->toArray();
                if ($success) {
                    self::assertSame($responseBytes, strlen(json_encode($actual)));
                    $expected = $response->toArray();
                    if ($method === 'prompts/list') {
                        // Budgets are authenticated serving parameters; only token length remains identical.
                        self::assertSame(48, strlen($actual['result']['nextCursor']));
                        unset($actual['result']['nextCursor'], $expected['result']['nextCursor']);
                    }
                    self::assertSame($expected, $actual);
                } else { self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $actual['error']); }
            }
            // The same capability receives each responder's current identity, never a cached identity.
            $small = $this->capability($providers, new PromptAvailability(), limits: new McpPromptLimits(pageSize: 1, maxResultBytes: $resultBytes - 1));
            self::assertArrayHasKey('result', (new McpResponder(new McpCapabilityRegistry(new McpServerInfo('s', '1'), [$small])))->respond($wire)->toArray());
            self::assertArrayHasKey('error', (new McpResponder(new McpCapabilityRegistry($info, [$small])))->respond($wire)->toArray());
        }
        $tiny = $this->capability([$provider], new PromptAvailability(), limits: new McpPromptLimits(maxResultBytes: 1));
        self::assertSame(-32603, $this->respond($tiny)['error']['code']);
    }

    #[DataProvider('invalidConfiguration')]
    public function test_that_conflicting_or_unbounded_registration_rejects(array $arguments): void
    {
        $this->expectException(DomainException::class);
        new McpPromptDiscovery(...[...['prompts' => [], 'availability' => new PromptAvailability(), 'cursorKey' => self::KEY, 'cursorScope' => 'test'], ...$arguments]);
    }

    public static function invalidConfiguration(): iterable
    {
        foreach ([['cursorKey' => 'short'], ['cursorKey' => str_repeat('k', 4097)], ['cursorScope' => ''], ['cursorScope' => str_repeat('x', 129)], ['prompts' => ['named' => null]], ['prompts' => [null]]] as $args) { yield [$args]; }
        $a = new PromptProvider(McpPromptInfo::fromArray(['name' => 'A']));
        yield [['prompts' => [$a, $a]]];
        yield [['prompts' => [$a, new PromptProvider(McpPromptInfo::fromArray(['name' => 'A']))]]];
        yield [['prompts' => [$a, $a], 'limits' => new McpPromptLimits(maxPrompts: 1)]];
        yield [['prompts' => [$a], 'limits' => new McpPromptLimits(maxCatalogBytes: strlen(json_encode($a->definition->toArray())) - 1)]];
    }

    public function test_that_exact_count_and_catalog_byte_limits_include_concealed_metadata(): void
    {
        $providers = $this->providers();
        $bytes = array_sum(array_map(fn($provider) => strlen(json_encode($provider->definition->toArray())), $providers));
        $capability = $this->capability($providers, new PromptAvailability(), limits: new McpPromptLimits(maxPrompts: 3, maxCatalogBytes: $bytes));
        self::assertCount(2, $capability->handle($this->request())->toArray()['prompts']);
        $this->expectException(DomainException::class);
        $this->capability($providers, new PromptAvailability(), limits: new McpPromptLimits(maxCatalogBytes: $bytes - 1));
    }

    private function providers(): array
    {
        return [new PromptProvider(McpPromptInfo::fromArray(['name' => 'Zulu'])), new PromptProvider(McpPromptInfo::fromArray(['name' => 'Hidden', 'description' => 'private catalog'])), new PromptProvider(McpPromptInfo::fromArray(['name' => 'Alpha', 'arguments' => [['name' => 'id', 'required' => true], ['name' => 'optional']]]))];
    }

    private function capability(array $providers, PromptAvailability $policy, ?McpPromptLimits $limits = null, string $scope = 'test', string $key = self::KEY): McpPromptDiscovery
    {
        return new McpPromptDiscovery($providers, $policy, $key, $scope, $limits ?? new McpPromptLimits(pageSize: 1));
    }

    private function respond(McpPromptDiscovery $capability, array $params = [], string $method = 'prompts/list'): array
    {
        return (new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [$capability])))->respond($this->wire($params, $method))->toArray();
    }

    private function request(array $params = [], string $method = 'prompts/list'): McpRequest
    {
        return (new McpRequestDecoder())->decode($this->wire($params, $method));
    }

    private function wire(array $params = [], string $method = 'prompts/list', int|string $id = 7): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => [...$params, '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR);
    }
}

final class PromptAvailability implements McpPromptAvailability
{
    public array $seen = [];
    public ?Throwable $failure = null;
    public function __construct(public string $hidden = 'Hidden') {}
    public function isAvailable(McpPromptInfo $prompt): bool
    {
        $this->seen[] = $prompt->name();
        if ($this->failure !== null) { throw $this->failure; }
        return $prompt->name() !== $this->hidden;
    }
}
