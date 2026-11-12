<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Http;

use Fight\Common\Adapter\Mcp\Http\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Mcp\Http\McpRequestHandler;
use Fight\Common\Adapter\Mcp\Http\McpResponseFactory;
use Fight\Common\Application\Mcp\Capability\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\Http\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\Protocol\McpServerInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceProvider;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

#[CoversNothing]
final class McpResourceMetadataIntegrationTest extends TestCase
{
    #[DataProvider('malformedProviderMetadata')]
    public function test_that_malformed_provider_metadata_never_becomes_a_successful_page(array $metadata): void
    {
        $resourceProvider = new class ($metadata) implements McpResourceProvider {
            public int $enumerations = 0;
            public function __construct(private readonly array $metadata) {}
            public function resources(): iterable {
                ++$this->enumerations;
                yield McpResourceInfo::fromArray(['uri' => 'context:/a', 'name' => 'Valid first descriptor']);
                yield McpResourceInfo::fromArray(['uri' => 'context:/secret', 'name' => 'Private descriptor', ...$this->metadata]);
            }
        };
        $availability = new class implements McpResourceAvailability {
            public function isAvailable(McpResourceInfo $resource): bool { return true; }
        };
        $guard = new class implements McpInvocationGuard {
            public function allows(string $method, ?string $name): bool { return true; }
        };
        $diagnostics = new class implements McpDiagnostics {
            public array $events = [];
            public function record(Throwable $failure): void { $this->events[] = 'resource_failure'; }
        };
        $factory = new HttpFactory();
        $endpoint = new McpRequestHandler(
            new McpCapabilityRegistry(new McpServerInfo('Resource validation', '1'), [new McpResourceDiscovery(
                [$resourceProvider], $availability, 'fixture-only-resource-cursor-key-00117', 'validation', new McpResourceLimits(pageSize: 1),
            )]),
            new ExactMcpOriginPolicy([]), $guard, new McpResponseFactory($factory, $factory, $diagnostics),
        );
        $response = $endpoint->handle($this->request('resources/list'));
        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32603, 'message' => 'Internal error.']], json_decode((string) $response->getBody(), true));
        self::assertSame(1, $resourceProvider->enumerations);
        self::assertSame(['resource_failure'], $diagnostics->events);
    }

    public static function malformedProviderMetadata(): iterable
    {
        foreach (['bad key', '1bad.example/name', 'org.example/-bad'] as $key) {
            yield 'R1 '.$key => [['_meta' => [$key => 'private extension value']]];
        }
        foreach (['data:image/p#ng;base64,YQ==', 'data:image/png;x=a#b;base64,YQ=='] as $source) {
            yield 'R2 '.$source => [['icons' => [['src' => $source]]]];
        }
    }

    private function request(string $method, array $parameters = []): ServerRequestInterface
    {
        return new ServerRequest('POST', 'https://server.test/consumer/resources', [
            'Authorization' => 'Bearer fixture-only', 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method,
        ], json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => [...$parameters, '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) [],
        ]]], JSON_THROW_ON_ERROR));
    }
}
