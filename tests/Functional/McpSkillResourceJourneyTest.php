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
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillAvailability;
use Fight\Common\Application\Mcp\Skill\McpSkillResources;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\ReadableResources;
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
final class McpSkillResourceJourneyTest extends TestCase
{
    public function test_that_guarded_endpoint_lazily_serves_complete_revisions_without_advertising_skills(): void
    {
        $revision = SkillRevision::create();
        $new = SkillRevision::create('v2');
        $policy = new class implements McpSkillAvailability {
            public bool $allowed = true;
            public bool $fail = false;
            public array $calls = [];
            public function isAvailable(StrictJson $entry): bool {
                $this->calls[] = $entry;
                if ($this->fail) { throw new RuntimeException('private consumer diagnostic'); }
                return $this->allowed;
            }
        };
        $streams = new class implements StreamFactoryInterface {
            public array $opened = [];
            public bool $corrupt = false;
            public function createStream(string $content = ''): StreamInterface {
                $this->opened[] = $content;
                return Utils::streamFor($this->corrupt ? str_repeat('x', strlen($content)) : $content);
            }
            public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface { throw new RuntimeException('No filesystem reads.'); }
            public function createStreamFromResource($resource): StreamInterface { throw new RuntimeException('No external resources.'); }
        };
        $provider = new McpSkillResources([$new, $revision], $policy, $streams);
        $ordinary = new ReadableResources(['document:/planning' => ['bytes' => 'Consumer planning']]);
        $availability = new class implements McpResourceAvailability {
            public function isAvailable(McpResourceInfo $resource): bool { return true; }
        };
        $guard = new class implements McpInvocationGuard {
            public bool $allowed = true;
            public array $calls = [];
            public function allows(string $method, ?string $name): bool { $this->calls[] = [$method, $name]; return $this->allowed; }
        };
        $diagnostics = new class implements McpDiagnostics {
            public array $events = [];
            public function record(Throwable $failure): void { $this->events[] = 'sanitized_skill_failure'; }
        };
        $factory = new HttpFactory();
        $capability = new McpResourceDiscovery([$provider, $ordinary], $availability, str_repeat('fixture-key', 4), 'skills', new McpResourceLimits(pageSize: 3), readLimits: new McpResourceReadLimits());
        $endpoint = new McpRequestHandler(new McpCapabilityRegistry(new McpServerInfo('Skill Resources', '1'), [$capability]), new ExactMcpOriginPolicy(['https://client.test']), $guard, new McpResponseFactory($factory, $factory, $diagnostics));
        $app = new App($factory);
        $app->post('/consumer/mcp', fn(ServerRequestInterface $request) => $endpoint->handle($request));
        $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($factory) {
            return $request->getHeaderLine('Authorization') === 'Bearer fixture-only' ? $next->handle($request) : $factory->createResponse(401);
        });
        $uri = $revision->entry()->get('uri');
        $request = $this->request('resources/read', ['uri' => $uri]);
        foreach ([[$request->withoutHeader('Authorization'), 401], [$request->withHeader('Origin', 'https://evil.test'), 403], [$request->withoutHeader('Mcp-Name'), 400], [$request->withHeader('Mcp-Name', 'skill://catalog/v2/work/SKILL.md'), 400]] as [$rejected, $status]) {
            self::assertSame($status, $app->handle($rejected)->getStatusCode());
            self::assertSame([], $streams->opened);
            self::assertSame([], $policy->calls);
            self::assertSame([], $guard->calls);
        }
        $guard->allowed = false;
        self::assertSame(429, $app->handle($request)->getStatusCode());
        self::assertSame([], $policy->calls);
        self::assertSame([], $streams->opened);
        $guard->allowed = true;

        // Direct root read before discovery, followed by only one chosen nested file.
        $root = $this->responseResult($app, 'resources/read', ['uri' => $uri]);
        self::assertSame(SkillRevision::ROOT, $root['contents'][0]['text']);
        self::assertSame(SkillRevision::GUIDE, $this->responseResult($app, 'resources/read', ['uri' => 'skill://catalog/v1/work/references/guide.md'])['contents'][0]['text']);
        self::assertSame([SkillRevision::ROOT, SkillRevision::GUIDE], $streams->opened);
        self::assertSame([], $ordinary->opened);
        self::assertSame('private', $root['cacheScope']); self::assertSame(0, $root['ttlMs']);
        self::assertSame('complete', $root['resultType']);
        self::assertSame(['resources' => ['subscribe' => false, 'listChanged' => false]], $this->responseResult($app, 'server/discover')['capabilities']);
        self::assertSame(-32601, $this->response($app, 'skills/list')['error']['code']);
        self::assertSame(-32601, $this->response($app, 'skills/get', ['uri' => $uri])['error']['code']);
        self::assertSame([], $this->responseResult($app, 'resources/templates/list')['resourceTemplates']);

        $addresses = []; $params = [];
        do {
            $list = $this->responseResult($app, 'resources/list', $params);
            $addresses = [...$addresses, ...array_column($list['resources'], 'uri')];
            $params = isset($list['nextCursor']) ? ['cursor' => $list['nextCursor']] : [];
        } while ($params !== []);
        self::assertCount(15, $addresses);
        self::assertSame([SkillRevision::ROOT, SkillRevision::GUIDE], $streams->opened);
        $expected = ['document:/planning', ...array_keys($revision->resources()), ...array_keys($new->resources())]; sort($expected, SORT_STRING);
        self::assertSame($expected, $addresses);

        // Separate integrity proof for every member; this is not automatic client prefetch.
        foreach ([$revision, $new] as $snapshot) {
            $manifest = json_decode($snapshot->entry()->toString(), true)['resources'];
            foreach ($manifest as $member) {
                $content = $this->responseResult($app, 'resources/read', ['uri' => $member['uri']])['contents'][0];
                $bytes = $content['text'] ?? base64_decode($content['blob'], true);
                self::assertSame($snapshot->file($member['uri'])->bytes, $bytes);
                self::assertSame($member['size'], strlen($bytes));
                self::assertSame($member['digest'], 'sha256:'.hash('sha256', $bytes));
            }
        }
        self::assertSame(SkillRevision::ROOT, $this->responseResult($app, 'resources/read', ['uri' => $uri])['contents'][0]['text']);
        self::assertSame('Consumer planning', $this->responseResult($app, 'resources/read', ['uri' => 'document:/planning'])['contents'][0]['text']);

        $policy->allowed = false;
        $opened = $streams->opened;
        self::assertSame(['document:/planning'], array_column($this->responseResult($app, 'resources/list')['resources'], 'uri'));
        $unknown = $this->response($app, 'resources/read', ['uri' => 'skill://catalog/missing/work/SKILL.md']);
        foreach ([...array_keys($revision->resources()), ...array_keys($new->resources()), 'skill://catalog/v1/work/../work/SKILL.md', 'skill://catalog/v1/work/%53KILL.md', 'skill://catalog/v1/work/%252e%252e/secret', 'https://127.0.0.1/private'] as $target) {
            self::assertSame($unknown, $this->response($app, 'resources/read', ['uri' => $target]));
        }
        self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $unknown['error']);
        self::assertSame($opened, $streams->opened);
        self::assertSame([], $diagnostics->events);

        $policy->allowed = true; $policy->fail = true;
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $this->response($app, 'resources/read', ['uri' => $uri])['error']);
        self::assertSame($opened, $streams->opened);
        $policy->fail = false; $streams->corrupt = true;
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $this->response($app, 'resources/read', ['uri' => $uri])['error']);
        self::assertSame(['sanitized_skill_failure', 'sanitized_skill_failure'], $diagnostics->events);
        $streams->corrupt = false;
        self::assertSame(SkillRevision::ROOT, $this->responseResult($app, 'resources/read', ['uri' => $uri])['contents'][0]['text']);
    }

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
