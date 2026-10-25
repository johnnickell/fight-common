<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Prompt\McpPrompt;
use Fight\Common\Application\Mcp\Prompt\McpPromptAvailability;
use Fight\Common\Application\Mcp\Prompt\McpPromptDiscovery;
use Fight\Common\Application\Mcp\Prompt\McpPromptInfo;
use Fight\Common\Application\Mcp\Prompt\McpPromptLimits;
use Fight\Common\Application\Mcp\Prompt\McpPromptOutput;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillAvailability;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscovery;
use Fight\Common\Application\Mcp\Skill\McpSkillResources;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Domain\Serialization\SampleQuery;
use Fight\Test\Common\Fixture\Mcp\DocumentLinkTool;
use Fight\Test\Common\Fixture\Mcp\PromptProvider;
use Fight\Test\Common\Fixture\Mcp\ReadableResources;
use Fight\Test\Common\Fixture\Mcp\SkillRevision;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\App;
use Throwable;

#[CoversNothing]
final class McpPromptJourneyTest extends TestCase
{
    public function test_that_authenticated_guarded_http_serves_complete_lazy_prompts_with_independent_query_and_resource_authority(): void
    {
        $factory = new HttpFactory();
        $handler = new class implements QueryHandler {
            public array $calls = [];
            public bool $allowed = true;
            public static function queryRegistration(): string { return SampleQuery::class; }
            public function handle(QueryMessage $queryMessage): mixed
            {
                $this->calls[] = $queryMessage->payload()->toArray()['value'];
                if (!$this->allowed) { throw new RuntimeException('private application permission and credential'); }
                return ['summary' => 'Safe task 雪', 'private' => 'do not disclose'];
            }
        };
        $router = new InMemoryQueryRouter();
        $router->registerHandler(SampleQuery::class, $handler);
        $queries = new RoutingQueryBus($router);
        $revision = SkillRevision::create();
        $skillUri = $revision->entry()->get('uri');
        $link = McpResourceInfo::fromArray(['uri' => $skillUri, 'name' => 'Work Skill', 'mimeType' => 'text/markdown']);
        $provider = new class($queries, $link) implements McpPrompt {
            public array $calls = [];
            public function __construct(private QueryBus $queries, private McpResourceInfo $link) {}
            public function info(): McpPromptInfo { return McpPromptInfo::fromArray(['name' => 'task.review', 'description' => 'Review one permitted task', 'arguments' => [['name' => 'id', 'required' => true], ['name' => 'note']]]); }
            public function get(array $arguments): McpPromptOutput
            {
                $this->calls[] = $arguments;
                $result = $this->queries->fetch(new SampleQuery($arguments['id']));
                return McpPromptOutput::fromArray(['description' => 'Review', 'messages' => [
                    ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Review '.$arguments['id'].': '.$result['summary'].($arguments['note'] ?? '')]],
                    ['role' => 'assistant', 'content' => ['type' => 'resource_link', ...$this->link->toArray()]],
                ]]);
            }
        };
        $static = new PromptProvider(McpPromptInfo::fromArray(['name' => 'static.start']));
        $static->output = McpPromptOutput::fromArray(['messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Start here.']]]]);
        $hidden = new PromptProvider(McpPromptInfo::fromArray(['name' => 'hidden.internal', 'description' => 'concealed declaration']));
        $promptAccess = new class implements McpPromptAvailability {
            public bool $allowed = true;
            public function isAvailable(McpPromptInfo $prompt): bool { return $this->allowed && $prompt->name() !== 'hidden.internal'; }
        };
        $resourceAccess = new class implements McpResourceAvailability {
            public bool $allowed = true;
            public function isAvailable(McpResourceInfo $resource): bool { return $this->allowed; }
        };
        $skillAccess = new class implements McpSkillAvailability {
            public function isAvailable(StrictJson $entry): bool { return true; }
        };
        $toolAccess = new class implements McpToolAvailability {
            public function isAvailable(McpToolInfo $tool): bool { return true; }
        };
        $guard = new class implements McpInvocationGuard {
            public bool $allowed = true;
            public array $calls = [];
            public function allows(string $method, ?string $name): bool { $this->calls[] = [$method, $name]; return $this->allowed; }
        };
        $diagnostics = new class implements McpDiagnostics {
            public int $calls = 0;
            public function record(Throwable $failure): void { ++$this->calls; }
        };
        $skillResources = new McpSkillResources([$revision], $skillAccess, $factory);
        $documents = new ReadableResources(['doc://guide' => ['bytes' => 'guide']]);
        $resources = new McpResourceDiscovery([$documents, $skillResources], $resourceAccess, str_repeat('resource-key', 4), 'prompts', readLimits: new McpResourceReadLimits());
        $skills = new McpSkillDiscovery($skillResources, $resources, str_repeat('skill-key', 4), 'prompts');
        $tools = new McpToolRegistry([new DocumentLinkTool($queries, $link)]);
        $prompts = new McpPromptDiscovery([$provider, $hidden, $static], $promptAccess, str_repeat('prompt-key', 4), 'journey', new McpPromptLimits(pageSize: 1));
        $registry = new McpCapabilityRegistry(new McpServerInfo('Combined Prompts', '1'), [
            $prompts, $resources, $skills,
            new McpToolDiscovery($tools, $toolAccess, str_repeat('tool-key', 4)),
            new McpToolInvocation(new McpToolInvoker($tools, $toolAccess)),
        ]);
        $endpoint = new McpRequestHandler($registry, new ExactMcpOriginPolicy(['https://client.test']), $guard, new McpResponseFactory($factory, $factory, $diagnostics));
        $app = new App($factory);
        $app->post('/consumer/mcp', fn(ServerRequestInterface $request) => $endpoint->handle($request));
        $app->add(function(ServerRequestInterface $request, RequestHandlerInterface $next) use ($factory) {
            return $request->getHeaderLine('Authorization') === 'Bearer fixture-only' ? $next->handle($request) : $factory->createResponse(401);
        });
        self::assertSame([], $provider->calls);
        self::assertSame([], $handler->calls);
        self::assertSame(0, $documents->enumerations);
        self::assertSame([], $documents->opened);

        // Direct retrieval precedes any discovery. No linked Resource provider is enumerated or opened.
        $get = $this->response($app, 'prompts/get', ['name' => 'task.review', 'arguments' => (object) ['id' => '42', 'note' => "\r\n雪"]])['result'];
        self::assertSame('complete', $get['resultType']);
        self::assertSame(['user', 'assistant'], array_column($get['messages'], 'role'));
        self::assertSame("Review 42: Safe task 雪\r\n雪", $get['messages'][0]['content']['text']);
        self::assertSame(['type' => 'resource_link', ...$link->toArray()], $get['messages'][1]['content']);
        self::assertSame(['42'], $handler->calls);
        self::assertSame(0, $documents->enumerations);
        self::assertSame([], $documents->opened);
        self::assertStringNotContainsString('do not disclose', json_encode($get));
        self::assertSame('Start here.', $this->response($app, 'prompts/get', ['name' => 'static.start'])['result']['messages'][0]['content']['text']);
        self::assertSame(['42'], $handler->calls);

        $discovery = $this->response($app, 'server/discover')['result']['capabilities'];
        self::assertSame(['listChanged' => false], $discovery['prompts']);
        self::assertArrayHasKey('tools', $discovery);
        self::assertArrayHasKey('resources', $discovery);
        self::assertArrayHasKey('io.modelcontextprotocol/skills', $discovery['extensions']);
        self::assertArrayNotHasKey('tasks', $discovery);
        self::assertArrayNotHasKey('completions', $discovery);
        $first = $this->response($app, 'prompts/list')['result'];
        self::assertSame(['static.start'], array_column($first['prompts'], 'name'));
        self::assertSame(0, $first['ttlMs']);
        self::assertSame('private', $first['cacheScope']);
        $second = $this->response($app, 'prompts/list', ['cursor' => $first['nextCursor']])['result'];
        self::assertSame(['task.review'], array_column($second['prompts'], 'name'));
        self::assertArrayNotHasKey('nextCursor', $second);
        self::assertCount(1, $provider->calls);
        self::assertSame([], $hidden->calls);
        self::assertSame(-32602, $this->response($app, 'prompts/list', ['cursor' => str_repeat('x', 48)])['error']['code']);
        foreach ([['name' => 'task.review'], ['name' => 'task.review', 'arguments' => (object) ['id' => 42]], ['name' => 'task.review', 'arguments' => (object) ['id' => str_repeat('x', 16385)]], ['name' => 'task.review', 'arguments' => (object) ['id' => '42', 'principal' => 'admin']], ['name' => 'hidden.internal'], ['name' => 'unknown']] as $invalid) {
            self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $this->response($app, 'prompts/get', $invalid)['error']);
        }
        self::assertCount(1, $provider->calls);
        self::assertSame(['42'], $handler->calls);

        foreach ([
            $this->request('prompts/get', ['name' => 'task.review'])->withoutHeader('Authorization'),
            $this->request('prompts/get', ['name' => 'task.review'])->withoutHeader('Mcp-Name'),
            $this->request('prompts/get', ['name' => 'task.review'])->withHeader('Mcp-Name', 'static.start'),
            $this->request('prompts/list')->withHeader('Origin', 'https://evil.test'),
        ] as $bad) { self::assertNotSame(200, $app->handle($bad)->getStatusCode()); }
        $guard->allowed = false;
        self::assertSame(429, $app->handle($this->request('prompts/get', ['name' => 'static.start']))->getStatusCode());
        $guard->allowed = true;
        self::assertCount(1, $provider->calls);

        $promptAccess->allowed = false;
        self::assertSame([], $this->response($app, 'prompts/list')['result']['prompts']);
        self::assertSame(-32602, $this->response($app, 'prompts/list', ['cursor' => $first['nextCursor']])['error']['code']);
        self::assertSame(-32602, $this->response($app, 'prompts/get', ['name' => 'task.review', 'arguments' => (object) ['id' => '42']])['error']['code']);
        self::assertCount(1, $provider->calls);
        $promptAccess->allowed = true;
        $handler->allowed = false;
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $this->response($app, 'prompts/get', ['name' => 'task.review', 'arguments' => (object) ['id' => '42']])['error']);
        self::assertSame(1, $diagnostics->calls);
        $handler->allowed = true;

        // Possession of the earlier successful link does not bypass later Resource authorization.
        $resourceAccess->allowed = false;
        self::assertSame(-32602, $this->response($app, 'resources/read', ['uri' => $get['messages'][1]['content']['uri']])['error']['code']);
        self::assertSame([], $documents->opened);
        $resourceAccess->allowed = true;
        self::assertSame($revision->file($skillUri)->bytes, $this->response($app, 'resources/read', ['uri' => $skillUri])['result']['contents'][0]['text']);
        self::assertNotEmpty($this->response($app, 'resources/list')['result']['resources']);
        self::assertSame($skillUri, $this->response($app, 'skills/get', ['uri' => $skillUri])['result']['skill']['uri']);
        self::assertCount(1, $this->response($app, 'skills/list')['result']['skills']);
        self::assertCount(1, $this->response($app, 'tools/list')['result']['tools']);
        $tool = $this->response($app, 'tools/call', ['name' => 'document.find', 'arguments' => (object) ['id' => 'guide']])['result'];
        self::assertSame(['summary' => 'Safe task 雪'], $tool['structuredContent']);
        self::assertSame(['42', '42', 'guide'], $handler->calls);
        self::assertSame([['id' => '42', 'note' => "\r\n雪"], ['id' => '42']], $provider->calls);
        self::assertSame([[]], $static->calls);
        self::assertSame([], $hidden->calls);
        self::assertContains(['prompts/get', 'task.review'], $guard->calls);
        // No CommandBus, event dispatcher, Tool executor, model or link-fetch collaborator enters Prompt composition.
    }

    public function test_that_guarded_http_enforces_complete_encoded_boundaries_and_rejects_unsupported_output_atomically(): void
    {
        $provider = new class implements McpPrompt {
            public array $output = ['messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => "雪\n\"\\"]]]];
            public function info(): McpPromptInfo { return McpPromptInfo::fromArray(['name' => 'Bounded', 'description' => '雪']); }
            public function get(array $arguments): McpPromptOutput { return McpPromptOutput::fromArray($this->output); }
        };
        $policy = new class implements McpPromptAvailability {
            public function isAvailable(McpPromptInfo $prompt): bool { return true; }
        };
        $guard = new class implements McpInvocationGuard {
            public function allows(string $method, ?string $name): bool { return true; }
        };
        $factory = new HttpFactory();
        $endpoint = function(McpPromptLimits $limits) use ($provider, $policy, $guard, $factory): McpRequestHandler {
            $capability = new McpPromptDiscovery([$provider], $policy, str_repeat('fixture-key', 4), 'bounds', $limits);
            return new McpRequestHandler(new McpCapabilityRegistry(new McpServerInfo(str_repeat('雪"', 40), '1'), [$capability]), new ExactMcpOriginPolicy(['https://client.test']), $guard, new McpResponseFactory($factory, $factory, new class implements McpDiagnostics { public function record(Throwable $failure): void {} }));
        };
        foreach (['prompts/list' => [], 'prompts/get' => ['name' => 'Bounded']] as $method => $params) {
            $request = $this->request($method, $params);
            $wire = json_decode((string) $request->getBody(), true);
            $wire['id'] = str_repeat('"', 100);
            $wire['params']['_meta']['io.modelcontextprotocol/clientCapabilities'] = (object) [];
            $request = $request->withBody($factory->createStream(json_encode($wire)));
            $normal = $endpoint(new McpPromptLimits())->handle($request);
            self::assertSame(200, $normal->getStatusCode());
            $encoded = (string) $normal->getBody();
            $bytes = strlen($encoded);
            $resultBytes = strlen(json_encode(json_decode($encoded, true)['result']));
            foreach ([[$resultBytes, $bytes, true], [$resultBytes - 1, $bytes, false], [$resultBytes, $bytes - 1, false]] as [$result, $response, $passes]) {
                $actual = $endpoint(new McpPromptLimits(maxResultBytes: $result, maxResponseBytes: $response))->handle($request);
                $body = json_decode((string) $actual->getBody(), true);
                if ($passes) { self::assertSame($encoded, (string) $actual->getBody()); }
                else {
                    self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $body['error']);
                    self::assertArrayNotHasKey('result', $body);
                }
            }
        }
        foreach ([
            ['messages' => [['role' => 'system', 'content' => ['type' => 'text', 'text' => 'concealed output']]]],
            ['messages' => [['role' => 'user', 'content' => ['type' => 'image', 'data' => 'private bytes']]]],
            ['messages' => [['role' => 'user', 'content' => ['type' => 'resource_link', 'uri' => '/private/path', 'name' => 'bad']]]],
            ['messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => str_repeat('x', 65537)]]]],
        ] as $invalid) {
            $provider->output = $invalid;
            $actual = $endpoint(new McpPromptLimits())->handle($this->request('prompts/get', ['name' => 'Bounded']));
            self::assertSame(['code' => -32603, 'message' => 'Internal error.'], json_decode((string) $actual->getBody(), true)['error']);
        }
    }

    private function response(App $app, string $method, array $params = []): array
    {
        return json_decode((string) $app->handle($this->request($method, $params))->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function request(string $method, array $params = []): ServerRequestInterface
    {
        $headers = ['Authorization' => 'Bearer fixture-only', 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method, 'Origin' => 'https://client.test'];
        if (isset($params['name'])) { $headers['Mcp-Name'] = $params['name']; }
        if ($method === 'resources/read') { $headers['Mcp-Name'] = $params['uri']; }
        return new ServerRequest('POST', 'https://server.test/consumer/mcp', $headers, json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => [...$params, '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR));
    }
}
