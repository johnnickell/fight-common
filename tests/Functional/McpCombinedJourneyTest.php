<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpRequestLimits;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillAvailability;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscovery;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscoveryLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillResources;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\DocumentLinkTool;
use Fight\Test\Common\Fixture\Mcp\ReadableResources;
use Fight\Test\Common\Fixture\Mcp\SkillRevision;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\App;
use Throwable;

#[CoversNothing]
final class McpCombinedJourneyTest extends TestCase
{
    public function test_that_one_guarded_endpoint_links_a_real_query_tool_to_independently_authorized_documents_and_skills(): void
    {
        $documentUri = 'doc://catalog/v1/guide';
        $bytes = "# Guide 雪\r\nOriginal bytes.\r\n";
        $documents = new ReadableResources([
            $documentUri => ['bytes' => $bytes, 'listed' => false],
            'doc://catalog/v1/a' => ['bytes' => 'A'],
            'doc://catalog/v1/b' => ['bytes' => 'B'],
        ]);
        $link = McpResourceInfo::fromArray(['uri' => $documentUri, 'name' => 'Guide', 'mimeType' => 'text/plain', 'size' => strlen($bytes)]);
        $revision = SkillRevision::create();
        $old = SkillRevision::create('v2');
        $skillsAvailable = new class implements McpSkillAvailability {
            public bool $allowed = true;
            public function isAvailable(StrictJson $entry): bool { return $this->allowed; }
        };
        $streams = new class implements StreamFactoryInterface {
            public array $opened = [];
            public function createStream(string $content = ''): StreamInterface { $this->opened[] = $content; return \GuzzleHttp\Psr7\Utils::streamFor($content); }
            public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface { throw new RuntimeException('No filesystem reads'); }
            public function createStreamFromResource($resource): StreamInterface { throw new RuntimeException('No external streams'); }
        };
        $provider = new McpSkillResources([$old, $revision], $skillsAvailable, $streams);
        $resourceAvailability = new class implements McpResourceAvailability {
            public bool $allowed = true;
            public function isAvailable(McpResourceInfo $resource): bool { return $this->allowed; }
        };
        $toolAvailability = new class implements McpToolAvailability {
            public bool $allowed = true;
            public function isAvailable(McpToolInfo $tool): bool { return $this->allowed; }
        };
        $queries = new class implements QueryBus {
            public array $ids = [];
            public function fetch(Query $query): mixed { return $this->dispatch(QueryMessage::create($query)); }
            public function dispatch(QueryMessage $message): mixed
            {
                $id = $message->payload()->toArray()['value'];
                $this->ids[] = $id;
                if ($id === 'explode') {
                    throw new RuntimeException('private query credential');
                }

                return ['summary' => 'Public summary', 'secret' => 'must not leak'];
            }
        };
        $guard = new class implements McpInvocationGuard {
            public bool $allowed = true;
            public function allows(string $method, ?string $name): bool { return $this->allowed; }
        };
        $diagnostics = new class implements McpDiagnostics {
            public array $events = [];
            public function record(Throwable $failure): void { $this->events[] = 'sanitized_failure'; }
        };
        $registry = new McpToolRegistry([new DocumentLinkTool($queries, $link)]);
        $resources = new McpResourceDiscovery([$documents, $provider], $resourceAvailability, str_repeat('resource-key', 4), 'combined', new McpResourceLimits(pageSize: 1), readLimits: new McpResourceReadLimits());
        $skills = new McpSkillDiscovery($provider, $resources, str_repeat('skill-key', 4), 'combined', new McpSkillDiscoveryLimits(pageSize: 1));
        $factory = new HttpFactory();
        $capabilities = new McpCapabilityRegistry(new McpServerInfo('Combined', '1'), [
                new McpToolDiscovery($registry, $toolAvailability, str_repeat('tool-key', 4)),
                new McpToolInvocation(new McpToolInvoker($registry, $toolAvailability)),
                $resources,
                $skills,
        ]);
        $endpoint = new McpRequestHandler(
            $capabilities, new ExactMcpOriginPolicy(['https://client.test']), $guard,
            new McpResponseFactory($factory, $factory, $diagnostics),
        );
        $app = new App($factory);
        $app->post('/consumer/mcp', fn(ServerRequestInterface $request) => $endpoint->handle($request));
        $app->add(function(ServerRequestInterface $request, RequestHandlerInterface $next) use ($factory) {
            return $request->getHeaderLine('Authorization') === 'Bearer fixture-only' ? $next->handle($request) : $factory->createResponse(401);
        });

        $discovery = $this->responseResult($app, 'server/discover');
        self::assertArrayHasKey('tools', $discovery['capabilities']);
        self::assertArrayHasKey('resources', $discovery['capabilities']);
        self::assertSame([], $discovery['capabilities']['extensions']['io.modelcontextprotocol/skills']);
        $call = $this->responseResult($app, 'tools/call', ['name' => 'document.find', 'arguments' => (object) ['id' => 'guide']]);
        self::assertSame(['guide'], $queries->ids);
        self::assertSame(['summary' => 'Public summary'], $call['structuredContent']);
        self::assertSame(['type' => 'resource_link', ...$link->toArray()], $call['content'][1]);
        self::assertSame('{"summary":"Public summary"}', $call['content'][0]['text']);
        self::assertSame('complete', $call['resultType']);
        self::assertStringNotContainsString('must not leak', json_encode($call));
        $document = $this->responseResult($app, 'resources/read', ['uri' => $call['content'][1]['uri']]);
        self::assertSame($bytes, $document['contents'][0]['text']);
        self::assertSame([$documentUri], $documents->opened);
        $resourcePage = $this->responseResult($app, 'resources/list');
        self::assertCount(1, $resourcePage['resources']);
        self::assertSame('private', $resourcePage['cacheScope']);
        self::assertSame(0, $resourcePage['ttlMs']);
        $resourceNext = $this->responseResult($app, 'resources/list', ['cursor' => $resourcePage['nextCursor']]);
        self::assertCount(1, $resourceNext['resources']);
        self::assertNotSame($resourcePage['resources'][0]['uri'], $resourceNext['resources'][0]['uri']);
        self::assertNotContains($documentUri, [$resourcePage['resources'][0]['uri'], $resourceNext['resources'][0]['uri']]);
        self::assertSame([$documentUri], $documents->opened);
        self::assertSame([], $streams->opened);
        $skillPage = $this->responseResult($app, 'skills/list');
        self::assertCount(1, $skillPage['skills']);
        $nextPage = $this->responseResult($app, 'skills/list', ['cursor' => $skillPage['nextCursor']]);
        self::assertCount(1, $nextPage['skills']);
        $skillUri = $old->entry()->get('uri');
        $entry = $this->responseResult($app, 'skills/get', ['uri' => $skillUri])['skill'];
        self::assertSame($skillUri, $entry['uri']);
        self::assertSame([], $streams->opened);
        $root = $this->responseResult($app, 'resources/read', ['uri' => $skillUri])['contents'][0]['text'];
        $nestedUri = 'skill://catalog/v2/work/references/guide.md';
        $nested = $this->responseResult($app, 'resources/read', ['uri' => $nestedUri])['contents'][0]['text'];
        self::assertSame([$old->file($skillUri)->bytes, $old->file($nestedUri)->bytes], $streams->opened);
        foreach ([$skillUri => $root, $nestedUri => $nested] as $uri => $content) {
            $manifest = array_column($entry['resources'], null, 'uri');
            self::assertSame(strlen($content), $manifest[$uri]['size']);
            self::assertSame('sha256:'.hash('sha256', $content), $manifest[$uri]['digest']);
        }
        self::assertContains('skill://catalog/v2/work/scripts/test.php', array_column($entry['resources'], 'uri'));
        self::assertContains('skill://catalog/v2/work/assets/image', array_column($entry['resources'], 'uri'));
        $binary = $this->responseResult($app, 'resources/read', ['uri' => 'skill://catalog/v2/work/assets/image'])['contents'][0]['blob'];
        self::assertSame(SkillRevision::BINARY, base64_decode($binary, true));
        self::assertSame('private', $document['cacheScope']);
        self::assertSame('private', $skillPage['cacheScope']);
        self::assertSame(0, $skillPage['ttlMs']);
        self::assertSame([], $diagnostics->events);

        $before = [$documents->opened, $streams->opened];
        $bounded = new McpRequestHandler(
            $capabilities, new ExactMcpOriginPolicy(['https://client.test']), $guard,
            new McpResponseFactory($factory, $factory, $diagnostics),
            new McpRequestDecoder(new McpRequestLimits(256)),
        );
        $oversized = $this->request('tools/call', [
            'name' => 'document.find', 'arguments' => (object) ['id' => str_repeat('x', 300)]
        ]);
        self::assertSame(413, $bounded->handle($oversized)->getStatusCode());
        self::assertSame(['guide'], $queries->ids);
        self::assertSame($before, [$documents->opened, $streams->opened]);
        foreach ([
            $this->request('resources/read', ['uri' => $skillUri])->withoutHeader('Mcp-Name'),
            $this->request('resources/read', ['uri' => $documentUri])->withHeader('Origin', 'https://evil.test'),
            $this->request('skills/get', ['uri' => $skillUri])->withoutHeader('Authorization'),
        ] as $bad) {
            self::assertNotSame(200, $app->handle($bad)->getStatusCode());
        }
        $guard->allowed = false;
        self::assertSame(429, $app->handle($this->request('resources/read', ['uri' => $skillUri]))->getStatusCode());
        $guard->allowed = true;
        self::assertSame($before, [$documents->opened, $streams->opened]);
        $resourceAvailability->allowed = false;
        self::assertSame(-32602, $this->response($app, 'resources/read', ['uri' => $documentUri])['error']['code']);
        self::assertSame(['summary' => 'Public summary'], $this->responseResult($app, 'tools/call', ['name' => 'document.find', 'arguments' => (object) ['id' => 'guide']])['structuredContent']);
        self::assertSame($before, [$documents->opened, $streams->opened]);
        $resourceAvailability->allowed = true;
        $toolAvailability->allowed = false;
        self::assertSame(-32602, $this->response($app, 'tools/call', ['name' => 'document.find', 'arguments' => (object) ['id' => 'guide']])['error']['code']);
        self::assertSame($bytes, $this->responseResult($app, 'resources/read', ['uri' => $documentUri])['contents'][0]['text']);
        $skillsAvailable->allowed = false;
        self::assertSame([], $this->responseResult($app, 'skills/list')['skills']);
        self::assertSame(-32602, $this->response($app, 'skills/get', ['uri' => $skillUri])['error']['code']);
        self::assertSame(-32602, $this->response($app, 'resources/read', ['uri' => $skillUri])['error']['code']);
        self::assertSame(-32602, $this->response($app, 'resources/read', ['uri' => $nestedUri])['error']['code']);
        self::assertSame(-32602, $this->response($app, 'resources/read', ['uri' => $old->entry()->get('uri')])['error']['code']);
        self::assertSame([$documentUri, $documentUri], $documents->opened);
        $documents->fail = true;
        $resourceAvailability->allowed = true;
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'],
            $this->response($app, 'resources/read', ['uri' => $documentUri])['error']);
        self::assertSame(['sanitized_failure'], $diagnostics->events);
        $toolAvailability->allowed = true;
        $badArguments = $this->response($app, 'tools/call', ['name' => 'document.find', 'arguments' => (object) []]);
        self::assertTrue($badArguments['result']['isError']);
        self::assertArrayNotHasKey('structuredContent', $badArguments['result']);
        self::assertCount(1, $badArguments['result']['content']);
        $toolAvailability->allowed = true;
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'],
            $this->response($app, 'tools/call', ['name' => 'document.find', 'arguments' => (object) ['id' => 'explode']])['error']);
        self::assertSame(['sanitized_failure', 'sanitized_failure'], $diagnostics->events);
    }

    private function responseResult(App $app, string $method, array $params = []): array
    {
        $response = $app->handle($this->request($method, $params));
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['result'];
    }

    private function response(App $app, string $method, array $params = []): array
    {
        return json_decode((string) $app->handle($this->request($method, $params))->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function request(string $method, array $params = []): ServerRequestInterface
    {
        $headers = ['Authorization' => 'Bearer fixture-only', 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method, 'Origin' => 'https://client.test'];
        if ($method === 'resources/read' && isset($params['uri'])) { $headers['Mcp-Name'] = $params['uri']; }
        if ($method === 'tools/call' && isset($params['name'])) { $headers['Mcp-Name'] = $params['name']; }
        return new ServerRequest('POST', 'https://server.test/consumer/mcp', $headers, json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => [...$params, '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR));
    }
}
