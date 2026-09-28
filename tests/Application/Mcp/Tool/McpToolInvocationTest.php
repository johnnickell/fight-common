<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Fight\Common\Adapter\Http\Mcp\McpHeaderValidator;
use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpRequestMirrors;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\Fixture\Mcp\EchoTool;
use Fight\Test\Common\Fixture\Mcp\MirroredTool;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpToolInvocation::class)]
#[CoversClass(McpToolRegistry::class)]
#[CoversClass(McpToolInvoker::class)]
#[CoversClass(McpHeaderValidator::class)]
final class McpToolInvocationTest extends UnitTestCase
{
    public function test_that_request_mirrors_are_selected_per_available_tool_without_invoking_it(): void
    {
        $tool = new MirroredTool();
        $registry = new McpToolRegistry([$tool, new EchoTool()]);
        $availability = new class implements McpToolAvailability {
            public bool $allowed = true;
            public function isAvailable(McpToolInfo $tool): bool { return $this->allowed; }
        };
        $invocation = new McpToolInvocation(new McpToolInvoker($registry, $availability));
        $headers = new McpHeaderValidator(new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [
            new McpToolDiscovery($registry, $availability, str_repeat('x', 32)), $invocation,
        ]));
        $arguments = (object) ['account' => (object) ['region' => 'west']];
        $base = ['MCP-Protocol-Version' => ['2026-07-28'], 'Mcp-Method' => ['tools/call']];
        $request = $this->request('mirrored', $arguments);
        self::assertSame('mirrored', $headers->validate($request, $base + ['Mcp-Name' => ['mirrored'], 'Mcp-Param-Region' => ['west']]));
        self::assertSame('echo', $headers->validate($this->request('echo', $arguments), $base + ['Mcp-Name' => ['echo']]));
        self::assertSame(0, $tool->calls);
        self::assertNull($registry->definition('absent'));
        self::assertSame([], $registry->mirrorsFor('absent'));
        try {
            $headers->validate($request, $base + ['Mcp-Name' => ['mirrored']]);
            self::fail('A selected Tool must validate its declared header.');
        } catch (McpProtocolException $failure) {
            self::assertSame(-32020, $failure->protocolError()->toArray()['code']);
        }
        $availability->allowed = false;
        $failures = [];
        foreach (['mirrored', 'absent'] as $name) {
            try {
                $headers->validate($this->request($name, $arguments), $base + ['Mcp-Name' => [$name]]);
                self::fail('Unavailable and missing Tools must conceal equally.');
            } catch (McpProtocolException $failure) {
                $failures[] = $failure->protocolError()->toArray();
            }
        }
        self::assertSame($failures[0], $failures[1]);
        self::assertSame(-32602, $failures[0]['code']);
        self::assertSame(0, $tool->calls);
    }

    public function test_that_progress_tokens_reject_until_the_progressive_capability_is_composed(): void
    {
        $availability = $this->mock(McpToolAvailability::class);
        $invocation = new McpToolInvocation(new McpToolInvoker(new McpToolRegistry([]), $availability));
        $this->expectException(McpProtocolException::class);
        $invocation->handle($this->request('echo', (object) [], 'progress-1'));
    }

    #[DataProvider('invalidProviderDeclarations')]
    public function test_that_request_mirror_providers_cannot_override_a_method_or_collide_with_static_headers(array $dynamic): void
    {
        $capability = new class ($dynamic) implements McpCapability, McpRequestMirrors {
            public function __construct(private array $dynamic) {}
            public function methods(): array { return ['example/mirrors']; }
            public function capabilities(): array { return ['example' => []]; }
            public function mirrorDeclarations(): array { return [new McpMirrorDeclaration('example/mirrors', ['value'], 'Value')]; }
            public function mirrorsFor(McpRequest $request): array { return $this->dynamic; }
            public function validate(McpRequest $request): void {}
            public function handle(McpRequest $request): McpResult { return McpResult::complete([]); }
        };
        $registry = new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [$capability]);
        $request = $this->request('unused', (object) []);
        $request = new McpRequest(7, 'example/mirrors', [], $request->metadata());
        $this->expectException(DomainException::class);
        new McpHeaderValidator($registry)->validate($request, ['MCP-Protocol-Version' => ['2026-07-28'], 'Mcp-Method' => ['example/mirrors']]);
    }

    public static function invalidProviderDeclarations(): iterable
    {
        yield [[new McpMirrorDeclaration('another/method', ['value'], 'Other')]];
        yield [[new McpMirrorDeclaration('example/mirrors', ['other'], 'value')]];
    }

    private function request(string $name, mixed $arguments, ?string $progress = null): McpRequest
    {
        $meta = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []];
        if ($progress !== null) {
            $meta['progressToken'] = $progress;
        }
        return new McpRequestDecoder()->decode(json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => [
            'name' => $name, 'arguments' => $arguments, '_meta' => $meta,
        ]], JSON_THROW_ON_ERROR));
    }
}
