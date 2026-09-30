<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillAvailability;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscovery;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscoveryLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillResources;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\SkillRevision;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Utils;
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
final class McpSkillDiscoveryJourneyTest extends TestCase
{
    public function test_that_guarded_discovery_get_and_selected_file_reads_preserve_complete_authorized_revisions(): void
    {
        $first = SkillRevision::create();
        $second = SkillRevision::create('v2');
        $policy = new class implements McpSkillAvailability {
            public bool $allowed = true;
            public bool $fail = false;
            public array $seen = [];
            public function isAvailable(StrictJson $entry): bool {
                $this->seen[] = $entry->get('uri');
                if ($this->fail) { throw new RuntimeException('private consumer policy details'); }
                return $this->allowed;
            }
        };
        $streams = new class implements StreamFactoryInterface {
            public array $opened = [];
            public function createStream(string $content = ''): StreamInterface { $this->opened[] = $content; return Utils::streamFor($content); }
            public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface { throw new RuntimeException('No filesystem reads'); }
            public function createStreamFromResource($resource): StreamInterface { throw new RuntimeException('No external streams'); }
        };
        $provider = new McpSkillResources([$second, $first], $policy, $streams);
        $general = new class implements McpResourceAvailability { public function isAvailable(McpResourceInfo $resource): bool { return true; } };
        $guard = new class implements McpInvocationGuard {
            public bool $allowed = true;
            public array $seen = [];
            public function allows(string $method, ?string $name): bool { $this->seen[] = [$method, $name]; return $this->allowed; }
        };
        $diagnostics = new class implements McpDiagnostics {
            public array $events = [];
            public function record(Throwable $failure): void { $this->events[] = 'sanitized_skill_failure'; }
        };
        $factory = new HttpFactory();
        $resources = new McpResourceDiscovery([$provider], $general, str_repeat('fixture-only', 4), 'resources', readLimits: new McpResourceReadLimits());
        $skills = new McpSkillDiscovery($provider, $resources, str_repeat('fixture-only', 4), 'skills', new McpSkillDiscoveryLimits(pageSize: 1));
        $endpoint = new McpRequestHandler(new McpCapabilityRegistry(new McpServerInfo('Skills journey', '1'), [$skills, $resources]), new ExactMcpOriginPolicy(['https://client.test']), $guard, new McpResponseFactory($factory, $factory, $diagnostics));
        $app = new App($factory);
        $app->post('/consumer/mcp', fn(ServerRequestInterface $request) => $endpoint->handle($request));
        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($factory) {
            return $request->getHeaderLine('Authorization') === 'Bearer fixture-only' ? $next->handle($request) : $factory->createResponse(401);
        });
        $uri = $first->entry()->get('uri');
        foreach (['skills/list' => [], 'skills/get' => ['uri' => $uri]] as $method => $params) {
            $request = $this->request($method, $params);
            foreach ([[$request->withoutHeader('Authorization'), 401], [$request->withHeader('Origin', 'https://evil.test'), 403], [$request->withoutHeader('Mcp-Method'), 400], [$request->withHeader('MCP-Protocol-Version', '2025-11-25'), 400]] as [$bad, $status]) {
                self::assertSame($status, $app->handle($bad)->getStatusCode());
                self::assertSame([], $streams->opened);
                self::assertSame([], $policy->seen);
                self::assertSame([], $guard->seen);
            }
        }
        $guard->allowed = false;
        self::assertSame(429, $app->handle($this->request('skills/get', ['uri' => $uri]))->getStatusCode());
        self::assertSame([], $policy->seen);
        self::assertSame([], $streams->opened);
        $guard->allowed = true;
        $discovery = $this->responseResult($app, 'server/discover');
        self::assertArrayHasKey('resources', $discovery['capabilities']);
        self::assertSame([], $discovery['capabilities']['extensions']['io.modelcontextprotocol/skills']);
        self::assertArrayNotHasKey('skills', $discovery['capabilities']);
        self::assertSame(-32601, $this->response($app, 'resources/directory/read', ['uri' => 'skill://catalog/v1/work'])['error']['code']);
        $page = $this->responseResult($app, 'skills/list');
        self::assertSame([$this->entry($first->entry())], $page['skills']);
        // No invented Mcp-Name mirror on skills/get, including a direct URI absent from this page.
        $get = $this->responseResult($app, 'skills/get', ['uri' => $second->entry()->get('uri')]);
        self::assertSame($this->entry($second->entry()), $get['skill']);
        self::assertArrayNotHasKey('nextCursor', $get);
        self::assertSame([$get['skill']], $this->responseResult($app, 'skills/list', ['cursor' => $page['nextCursor']])['skills']);
        self::assertSame([], $streams->opened);
        self::assertContains(['skills/get', null], $guard->seen);
        foreach ([$page, $get] as $result) {
            self::assertSame('complete', $result['resultType']);
            self::assertSame('private', $result['cacheScope']);
            self::assertSame(0, $result['ttlMs']);
        }
        $root = $this->responseResult($app, 'resources/read', ['uri' => $uri])['contents'][0]['text'];
        $guide = $this->responseResult($app, 'resources/read', ['uri' => 'skill://catalog/v1/work/references/guide.md'])['contents'][0]['text'];
        self::assertSame([SkillRevision::ROOT, SkillRevision::GUIDE], $streams->opened);
        foreach ([$uri => $root, 'skill://catalog/v1/work/references/guide.md' => $guide] as $address => $bytes) {
            $manifest = array_column($page['skills'][0]['resources'], null, 'uri');
            self::assertSame(strlen($bytes), $manifest[$address]['size']);
            self::assertSame('sha256:'.hash('sha256', $bytes), $manifest[$address]['digest']);
        }
        // Root mirrors still apply on Resource reads, before policy and content opening.
        $opened = $streams->opened;
        $seen = $policy->seen;
        self::assertSame(400, $app->handle($this->request('resources/read', ['uri' => $uri])->withoutHeader('Mcp-Name'))->getStatusCode());
        self::assertSame($opened, $streams->opened);
        self::assertSame($seen, $policy->seen);
        // Every retained revision's listed manifest remains a complete exact-byte contract.
        foreach ([$first, $second] as $revision) {
            $entry = $this->responseResult($app, 'skills/get', ['uri' => $revision->entry()->get('uri')])['skill'];
            foreach ($entry['resources'] as $member) {
                $content = $this->responseResult($app, 'resources/read', ['uri' => $member['uri']])['contents'][0];
                $bytes = $content['text'] ?? base64_decode($content['blob'], true);
                self::assertSame($revision->file($member['uri'])->bytes, $bytes);
                self::assertSame($member['size'], strlen($bytes));
                self::assertSame($member['digest'], 'sha256:'.hash('sha256', $bytes));
            }
        }
        self::assertSame($page['skills'][0], $this->responseResult($app, 'skills/get', ['uri' => $uri])['skill']);
        $policy->allowed = false;
        $opened = $streams->opened;
        self::assertSame([], $this->responseResult($app, 'skills/list')['skills']);
        self::assertSame(-32602, $this->response($app, 'skills/list', ['cursor' => $page['nextCursor']])['error']['code']);
        $unknown = $this->response($app, 'skills/get', ['uri' => 'skill://catalog/missing/work/SKILL.md']);
        foreach ([$first, $second] as $revision) {
            self::assertSame($unknown, $this->response($app, 'skills/get', ['uri' => $revision->entry()->get('uri')]));
            foreach (array_keys($revision->resources()) as $address) {
                self::assertSame($unknown['error'], $this->response($app, 'resources/read', ['uri' => $address])['error']);
            }
        }
        self::assertSame($opened, $streams->opened);
        self::assertSame([], $diagnostics->events);
        $policy->allowed = true;
        $policy->fail = true;
        foreach (['skills/list' => [], 'skills/get' => ['uri' => $uri]] as $method => $params) {
            self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $this->response($app, $method, $params)['error']);
        }
        self::assertSame($opened, $streams->opened);
        self::assertSame(['sanitized_skill_failure', 'sanitized_skill_failure'], $diagnostics->events);
        $policy->fail = false;
        self::assertSame($page['skills'], $this->responseResult($app, 'skills/list', ['cursor' => ''])['skills']);
    }

    private function entry(StrictJson $entry): array { return json_decode($entry->toString(), true, flags: JSON_THROW_ON_ERROR); }
    private function responseResult(App $app, string $method, array $params = []): array
    {
        $response = $this->response($app, $method, $params);
        self::assertArrayHasKey('result', $response);
        return $response['result'];
    }
    private function response(App $app, string $method, array $params = []): array
    {
        return json_decode((string) $app->handle($this->request($method, $params))->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
    private function request(string $method, array $params = []): ServerRequestInterface
    {
        $headers = ['Authorization' => 'Bearer fixture-only', 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method, 'Origin' => 'https://client.test'];
        if ($method === 'resources/read' && isset($params['uri'])) { $headers['Mcp-Name'] = $params['uri']; }
        return new ServerRequest('POST', 'https://server.test/consumer/mcp', $headers, json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => [...$params, '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR));
    }
}
