<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Adapter\Mcp\Http\DenyAllMcpOriginPolicy;
use Fight\Common\Application\Mcp as Legacy;
use Fight\Common\Application\Mcp\Capability\McpCapability;
use Fight\Common\Application\Mcp\Capability\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\Capability\McpMetadataAwareCapability;
use Fight\Common\Application\Mcp\Http\McpInvocationGuard;
use Fight\Common\Application\Mcp\Http\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\Http\McpOriginPolicy;
use Fight\Common\Application\Mcp\Http\McpRequestMirrors;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\Protocol\Exception\McpProtocolException;
use Fight\Common\Application\Mcp\Protocol\McpJsonResponse;
use Fight\Common\Application\Mcp\Protocol\McpProtocolError;
use Fight\Common\Application\Mcp\Protocol\McpRequest;
use Fight\Common\Application\Mcp\Protocol\McpRequestDecoder;
use Fight\Common\Application\Mcp\Protocol\McpRequestLimits;
use Fight\Common\Application\Mcp\Protocol\McpRequestMetadata;
use Fight\Common\Application\Mcp\Protocol\McpResult;
use Fight\Common\Application\Mcp\Protocol\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Mcp\Tool\NullMcpProgressReporter;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\EchoTool;
use Fight\Test\Common\Fixture\Mcp\EndpointCapability;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFile;
use ReflectionClass;

#[CoversClass(DenyAllMcpOriginPolicy::class)]
#[CoversClass(Legacy\McpCapabilityRegistry::class)]
#[CoversClass(Legacy\McpJsonResponse::class)]
#[CoversClass(Legacy\McpMirrorDeclaration::class)]
#[CoversClass(Legacy\McpProtocolError::class)]
#[CoversClass(Legacy\McpProtocolException::class)]
#[CoversClass(Legacy\McpRequest::class)]
#[CoversClass(Legacy\McpRequestDecoder::class)]
#[CoversClass(Legacy\McpRequestLimits::class)]
#[CoversClass(Legacy\McpRequestMetadata::class)]
#[CoversClass(McpResponder::class)]
#[CoversClass(Legacy\McpResult::class)]
#[CoversClass(Legacy\McpServerInfo::class)]
#[CoversClass(McpToolRegistry::class)]
#[CoversClass(NullMcpProgressReporter::class)]
#[CoversFile('src/Application/Mcp/Capability/McpCapability.php')]
#[CoversFile('src/Application/Mcp/Capability/McpCapabilityRegistry.php')]
#[CoversFile('src/Application/Mcp/Capability/McpMetadataAwareCapability.php')]
#[CoversFile('src/Application/Mcp/Http/McpInvocationGuard.php')]
#[CoversFile('src/Application/Mcp/Http/McpMirrorDeclaration.php')]
#[CoversFile('src/Application/Mcp/Http/McpOriginPolicy.php')]
#[CoversFile('src/Application/Mcp/Http/McpRequestMirrors.php')]
#[CoversFile('src/Application/Mcp/Protocol/Exception/McpProtocolException.php')]
#[CoversFile('src/Application/Mcp/Protocol/McpJsonResponse.php')]
#[CoversFile('src/Application/Mcp/Protocol/McpProtocolError.php')]
#[CoversFile('src/Application/Mcp/Protocol/McpRequest.php')]
#[CoversFile('src/Application/Mcp/Protocol/McpRequestDecoder.php')]
#[CoversFile('src/Application/Mcp/Protocol/McpRequestLimits.php')]
#[CoversFile('src/Application/Mcp/Protocol/McpRequestMetadata.php')]
#[CoversFile('src/Application/Mcp/Protocol/McpResult.php')]
#[CoversFile('src/Application/Mcp/Protocol/McpServerInfo.php')]
#[CoversFile('src/Application/Mcp/Tool/McpProgressReporter.php')]
final class McpFoundationCompatibilityTest extends UnitTestCase
{
    public function test_that_canonical_names_retain_released_reflection_identity_and_allow_repeated_loading(): void
    {
        $canonicalNames = [
            McpCapability::class, McpMetadataAwareCapability::class, McpCapabilityRegistry::class,
            McpRequest::class, McpRequestMetadata::class, McpRequestDecoder::class, McpRequestLimits::class,
            McpResult::class, McpJsonResponse::class, McpProtocolError::class, McpServerInfo::class,
            McpProtocolException::class, McpOriginPolicy::class, McpInvocationGuard::class,
            McpMirrorDeclaration::class, McpRequestMirrors::class, McpProgressReporter::class,
        ];
        foreach ($canonicalNames as $canonical) {
            $reflection = new ReflectionClass($canonical);
            $released = 'Fight\\Common\\Application\\Mcp\\'.substr($canonical, strrpos($canonical, '\\') + 1);
            self::assertSame($released, $reflection->getName());
            self::assertEquals((new ReflectionClass($released))->getMethods(), $reflection->getMethods());
            if (!$reflection->isInterface()) {
                self::assertTrue($reflection->isFinal());
            }
            // Consumer loaders may include an entry point after Composer's eager registration.
            $relative = str_replace('Fight\\Common\\', 'src/', $canonical);
            require dirname(__DIR__, 3).'/'.str_replace('\\', '/', $relative).'.php';
            self::assertSame($released, (new ReflectionClass($canonical))->getName());
        }
    }

    public function test_that_named_construction_factories_and_catches_work_in_both_directions(): void
    {
        $limits = (new ReflectionClass(McpRequestLimits::class))->newInstanceArgs(['maxBytes' => 4096, 'maxDepth' => 32]);
        self::assertInstanceOf(Legacy\McpRequestLimits::class, $limits);
        self::assertSame(4096, $limits->maxBytes());
        self::assertSame(32, $limits->maxDepth());
        $constructor = (new ReflectionClass(McpResponder::class))->getConstructor();
        self::assertSame(Legacy\McpCapabilityRegistry::class, $constructor->getParameters()[0]->getType()->getName());
        self::assertSame(Legacy\McpRequestDecoder::class, $constructor->getParameters()[1]->getType()->getName());
        $decoder = new McpRequestDecoder(limits: $limits);
        self::assertSame($limits, $decoder->limits());
        $metadata = McpRequestMetadata::fromObject(metadata: StrictJson::fromObject([
            McpRequestMetadata::PROTOCOL_VERSION_KEY => McpResponder::PROTOCOL_VERSION,
            McpRequestMetadata::CLIENT_CAPABILITIES_KEY => StrictJson::fromObject(),
        ]));
        $request = new McpRequest(id: 'r', method: 'example/echo', parameters: [], metadata: $metadata);
        self::assertInstanceOf(Legacy\McpRequest::class, $request);
        self::assertSame(Legacy\McpRequest::class, $request::class);
        self::assertSame($metadata, $request->metadata());
        $result = McpResult::complete(data: ['answer' => 42]);
        self::assertSame(Legacy\McpResult::class, $result::class);
        self::assertSame(['jsonrpc' => '2.0', 'id' => 'r', 'result' => ['resultType' => 'complete', 'answer' => 42]],
            McpJsonResponse::success(id: 'r', result: $result)->toArray());
        self::assertInstanceOf(McpResult::class, Legacy\McpResult::complete(data: []));
        self::assertSame(serialize(Legacy\McpResult::complete(['answer' => 42])), serialize($result));
        foreach ([McpProtocolException::class, Legacy\McpProtocolException::class] as $exceptionType) {
            try {
                throw new $exceptionType(protocolError: McpProtocolError::invalidParams(), requestId: 'r');
            } catch (Legacy\McpProtocolException $exception) {
                self::assertInstanceOf(McpProtocolException::class, $exception);
                self::assertSame('r', $exception->requestId());
                self::assertSame(McpProtocolError::INVALID_PARAMS, $exception->protocolError()->toArray()['code']);
            }
            try {
                throw new $exceptionType(protocolError: Legacy\McpProtocolError::parseError(), requestId: null);
            } catch (McpProtocolException $exception) {
                self::assertInstanceOf(Legacy\McpProtocolException::class, $exception);
                self::assertSame('Parse error.', $exception->getMessage());
            }
        }
    }

    public function test_that_legacy_and_canonical_capabilities_accept_each_others_requests_and_results(): void
    {
        $legacy = new class implements Legacy\McpMetadataAwareCapability, Legacy\McpRequestMirrors {
            public array $metadata = [];
            public function methods(): array { return ['example/echo']; }
            public function capabilities(): array { return ['example' => []]; }
            public function mirrorDeclarations(): array { return []; }
            public function mirrorsFor(Legacy\McpRequest $request): array { return $this->mirrorDeclarations(); }
            public function validate(Legacy\McpRequest $request): void {}
            public function handle(Legacy\McpRequest $request): Legacy\McpResult
            {
                return Legacy\McpResult::complete(['method' => $request->method()]);
            }
            public function handleWithMetadata(Legacy\McpRequest $request, array $metadata): Legacy\McpResult
            {
                $this->metadata = $metadata;
                return $this->handle($request);
            }
        };
        self::assertInstanceOf(McpMetadataAwareCapability::class, $legacy);
        self::assertInstanceOf(McpRequestMirrors::class, $legacy);
        $canonical = new EndpointCapability();
        $metadata = McpRequestMetadata::fromObject(StrictJson::fromObject([
            McpRequestMetadata::PROTOCOL_VERSION_KEY => McpResponder::PROTOCOL_VERSION,
            McpRequestMetadata::CLIENT_CAPABILITIES_KEY => StrictJson::fromObject(),
        ]));
        $oldRequest = new Legacy\McpRequest(7, 'example/echo', [], $metadata);
        $newRequest = new McpRequest(8, 'example/echo', [], $metadata);
        $oldConsumer = static fn (Legacy\McpCapability $capability, Legacy\McpRequest $request): Legacy\McpResult
            => $capability->handle($request);
        $newConsumer = static fn (McpCapability $capability, McpRequest $request): McpResult => $capability->handle($request);
        self::assertInstanceOf(McpResult::class, $oldConsumer($canonical, $newRequest));
        self::assertInstanceOf(Legacy\McpResult::class, $newConsumer($legacy, $oldRequest));
        self::assertSame([], $legacy->mirrorsFor($newRequest));
        $registry = new McpCapabilityRegistry(serverInfo: new McpServerInfo(name: 'Compat', version: '1'), capabilities: [$legacy]);
        self::assertSame($legacy, $registry->capabilityFor('example/echo'));
        self::assertSame('example/echo', (new McpResponder(registry: $registry))->dispatch($oldRequest)->toArray()['result']['method']);
        self::assertSame(['io.modelcontextprotocol/serverInfo' => ['name' => 'Compat', 'version' => '1']], $legacy->metadata);
    }

    public function test_that_legacy_policies_and_tool_progress_signatures_remain_usable(): void
    {
        $policy = new class implements Legacy\McpOriginPolicy, Legacy\McpInvocationGuard {
            public function allows(string $origin, ?string $name = null): bool { return $origin !== 'denied'; }
        };
        $check = static fn (McpOriginPolicy $origin, McpInvocationGuard $guard): bool
            => $origin->allows('https://example.com:443') && !$guard->allows('denied', null);
        self::assertTrue($check($policy, $policy));
        $oldPolicyConsumer = static fn (Legacy\McpOriginPolicy $origin): bool => $origin->allows('https://example.com:443');
        self::assertFalse($oldPolicyConsumer(new DenyAllMcpOriginPolicy()));
        $tool = new class implements McpTool {
            #[McpToolInfo(name: 'compat', description: 'Legacy reporter parameter', inputSchema: ['type' => 'object'], outputSchema: [])]
            public function handle(ApplicationData $input, Legacy\McpProgressReporter $progress): McpToolOutput
            {
                $progress->report(progress: 1, total: 1, message: 'Done');
                return McpToolOutput::structured(['cancelled' => $progress->isCancelled()]);
            }
        };
        $registry = new McpToolRegistry([$tool]);
        $reporter = new NullMcpProgressReporter();
        self::assertInstanceOf(McpProgressReporter::class, $reporter);
        self::assertInstanceOf(Legacy\McpProgressReporter::class, $reporter);
        self::assertSame($tool, $registry->find('compat'));
        self::assertSame(['cancelled' => false], $registry->find('compat')->handle(new ApplicationData([]), $reporter)->structuredContent()->properties());
        $legacyReporter = new class implements Legacy\McpProgressReporter {
            public function report(float $progress, ?float $total = null, ?string $message = null): void {}
            public function isCancelled(): bool { return false; }
        };
        $canonicalTool = new EchoTool();
        self::assertSame(['value' => 7], $canonicalTool->handle(new ApplicationData(['value' => 7]), $legacyReporter)->structuredContent()->properties());
        self::assertSame($legacyReporter, $canonicalTool->reporter);
    }

    public function test_that_tagged_saved_values_are_readable_and_remain_usable_through_canonical_types(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__, 2).'/Fixture/Mcp/serialized-foundation-v1.4.0.json'), true, flags: JSON_THROW_ON_ERROR);
        $values = array_map(static fn (string $frame): object => unserialize(base64_decode($frame, true)), $fixture['values']);
        self::assertInstanceOf(McpRequestMetadata::class, $values['metadata']);
        self::assertSame('saved-progress', $values['metadata']->progressToken());
        self::assertSame('2026-07-28', $values['metadata']->protocolVersion());
        self::assertInstanceOf(McpRequest::class, $values['request']);
        self::assertSame(['value' => 7], $values['request']->parameters());
        self::assertSame('example/read', $values['request']->method());
        self::assertSame('saved-id', $values['request']->id());
        self::assertSame('saved-progress', $values['request']->metadata()->progressToken());
        self::assertInstanceOf(McpRequestLimits::class, $values['limits']);
        self::assertSame(4096, $values['limits']->maxBytes());
        self::assertSame(32, $values['limits']->maxDepth());
        self::assertInstanceOf(McpResult::class, $values['result']);
        self::assertSame(['resultType' => 'complete', 'answer' => 42], $values['result']->toArray());
        self::assertSame($values['result']->toArray(), McpJsonResponse::success('restored', $values['result'])->toArray()['result']);
        self::assertSame(['restored' => true], $values['result']->withMetadata(['restored' => true])->toArray()['_meta']);
        self::assertInstanceOf(McpJsonResponse::class, $values['response']);
        self::assertSame('{"jsonrpc":"2.0","id":"saved-id","result":{"resultType":"complete","answer":42}}', $values['response']->toJson());
        self::assertInstanceOf(McpProtocolError::class, $values['error']);
        self::assertSame(['supported' => ['2026-07-28'], 'requested' => 'old'], $values['error']->toArray()['data']);
        self::assertInstanceOf(McpServerInfo::class, $values['server']);
        self::assertSame(['name' => 'Saved server', 'version' => '1.4'], $values['server']->toArray());
        self::assertInstanceOf(McpMirrorDeclaration::class, $values['mirror']);
        self::assertSame('example/read', $values['mirror']->method());
        self::assertSame(['value'], $values['mirror']->parameterPath());
        self::assertSame('X-Value', $values['mirror']->headerName());
        foreach ($values as $value) {
            $restored = unserialize(serialize($value));
            self::assertEquals($value, $restored);
            self::assertSame($value::class, $restored::class);
        }
        $bounded = McpResult::boundedComplete(['answer' => 42], 100)->withResponseLimit(500);
        $restored = unserialize(serialize($bounded));
        self::assertSame(500, $restored->maxEncodedResponseBytes());
        self::assertSame($bounded->toArray(), $restored->toArray());
    }
}
