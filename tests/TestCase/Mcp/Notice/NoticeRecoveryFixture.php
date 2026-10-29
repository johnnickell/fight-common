<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Notice;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Mcp\Http\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Mcp\Http\McpRequestHandler;
use Fight\Common\Adapter\Mcp\Http\McpResponseFactory;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeStore;
use Fight\Common\Adapter\Mcp\Notice\SodiumNoticeContinuationProtector;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Notice\QueryHandler\RecoverNoticesHandler;
use Fight\Common\Application\Mcp\Notice\RecoverNoticesTool;
use Fight\Common\Application\Mcp\Notice\Service\NoticeAccess;
use Fight\Common\Application\Mcp\Notice\Service\NoticeContinuationProtector;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Mcp\Notice\Service\NoticeRecovery;
use Fight\Common\Application\Mcp\Notice\Service\NoticeRecoveryIdentity;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Throwable;

final class NoticeRecoveryFixture implements NoticeReadAdmission, McpToolAvailability, McpInvocationGuard, McpDiagnostics
{
    public NoticeAccess $access;
    public readonly RoutingQueryBus $queries;
    public readonly McpRequestHandler $endpoint;
    public int $admissions = 0;
    public int $availabilityCalls = 0;
    public bool $available = true;
    public bool $guard = true;
    public array $diagnostics = [];
    public string $lastResponse = '';
    public string $requestId = 'notice-雪-"-\\';

    public function __construct(public readonly Connection $db, int $maxResultBytes = 65536, int $maxResponseBytes = 1048576, string $serverName = 'Notice fixture', ?NoticeContinuationProtector $protector = null)
    {
        $this->access = self::access();
        $router = new InMemoryQueryRouter();
        $recovery = new NoticeRecovery(new DbalNoticeStore($db), $protector ?? self::codec(), maxResultBytes: $maxResultBytes);
        $handler = new RecoverNoticesHandler($this, $recovery);
        $router->registerHandler($handler::queryRegistration(), $handler);
        $this->queries = new RoutingQueryBus($router);
        $tools = new McpToolRegistry([new RecoverNoticesTool($this->queries, $maxResponseBytes)]);
        $registry = new McpCapabilityRegistry(new McpServerInfo($serverName, '1'), [new McpToolDiscovery($tools, $this, str_repeat('catalog-key-', 4)), new McpToolInvocation(new McpToolInvoker($tools, $this))]);
        $factory = new HttpFactory();
        $this->endpoint = new McpRequestHandler($registry, new ExactMcpOriginPolicy(['https://client.test']), $this, new McpResponseFactory($factory, $factory, $this));
    }

    public static function codec(): SodiumNoticeContinuationProtector { return new SodiumNoticeContinuationProtector(['testkey1' => str_repeat('k', 32)], 'testkey1'); }
    public static function access(array $scopes = ['scope'], array $filters = [], string $caller = 'caller', ?string $revision = 'revision', bool $evidence = true): NoticeAccess { return NoticeAccess::create($scopes, $filters, new NoticeRecoveryIdentity($caller, $revision, $evidence)); }
    public function currentAccess(NoticeSelection $selection): NoticeAccess { ++$this->admissions; return $this->access; }
    public function isAvailable(McpToolInfo $tool): bool { ++$this->availabilityCalls; return $this->available; }
    public function allows(string $method, ?string $name): bool { return $this->guard; }
    public function record(Throwable $failure): void { $this->diagnostics[] = 'redacted'; }
    public function call(array $arguments): array { return $this->request('tools/call', ['name' => 'notices.recover', 'arguments' => (object) $arguments]); }

    public function request(string $method, array $params = [], array $headers = []): array
    {
        $headers += ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method, 'Origin' => 'https://client.test'];
        if ($method === 'tools/call') { $headers += ['Mcp-Name' => $params['name']]; }
        $body = json_encode(['jsonrpc' => '2.0', 'id' => $this->requestId, 'method' => $method, 'params' => $params + ['_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) [], 'fight/claimedRole' => 'SUPER_ADMIN']]], JSON_THROW_ON_ERROR);
        $response = $this->endpoint->handle(new ServerRequest('POST', 'https://server.test/consumer/mcp', $headers, $body));
        $this->lastResponse = (string) $response->getBody();
        return ['status' => $response->getStatusCode(), 'body' => json_decode($this->lastResponse, true, flags: JSON_THROW_ON_ERROR)];
    }

    public function state(): array { return array_map(fn ($table) => $this->db->fetchAllAssociative('SELECT * FROM '.$table), DbalNoticeSchema::TABLES); }
}
