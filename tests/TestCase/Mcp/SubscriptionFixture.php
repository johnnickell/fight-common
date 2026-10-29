<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp;

use Closure;
use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Mcp\Http\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Mcp\Http\McpRequestHandler;
use Fight\Common\Adapter\Mcp\Http\McpResponseFactory;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeStore;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionSchema;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionStore;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Notice\QueryHandler\RecoverNoticesHandler;
use Fight\Common\Application\Mcp\Notice\RecoverNoticesTool;
use Fight\Common\Application\Mcp\Notice\Service\NoticeAccess;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Mcp\Notice\Service\NoticeRecovery;
use Fight\Common\Application\Mcp\Prompt\McpPromptAvailability;
use Fight\Common\Application\Mcp\Prompt\McpPromptDiscovery;
use Fight\Common\Application\Mcp\Prompt\McpPromptInfo;
use Fight\Common\Application\Mcp\Prompt\McpPromptOutput;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionAdmission;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Test\Common\Fixture\Mcp\PromptProvider;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRecoveryFixture;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Throwable;

final class SubscriptionFixture implements McpSubscriptionAdmission, NoticeReadAdmission, McpDiagnostics
{
    public array $diagnostics = [];
    public bool $permitted = true;
    public bool $admit = true;
    public ?Closure $policy = null;
    public ?Closure $queryPolicy = null;
    public ?Closure $promptPolicy = null;
    public bool $promptPermitted = true;
    public readonly PromptProvider $visiblePrompt;
    public readonly PromptProvider $hiddenPrompt;
    public readonly McpRequestHandler $endpoint;
    public readonly McpSubscriptions $subscriptions;
    public readonly RoutingQueryBus $queries;
    public readonly DbalSubscriptionStore $store;
    public readonly McpCapabilityRegistry $registry;

    public function __construct(public readonly Connection $db, public readonly string $scope = 'scope', int $lifetime = 1, int $frameBytes = 16384, bool $tools = true, bool $resources = true, bool $prompts = false, bool $servePrompts = false)
    {
        (new DbalNoticeSchema())->install($db);
        (new DbalSubscriptionSchema())->install($db);
        $this->store = new DbalSubscriptionStore($db);
        $router = new InMemoryQueryRouter();
        $handler = new RecoverNoticesHandler($this, new NoticeRecovery(new DbalNoticeStore($db), NoticeRecoveryFixture::codec()));
        $router->registerHandler($handler::queryRegistration(), $handler);
        $this->queries = new RoutingQueryBus($router);
        $this->subscriptions = new McpSubscriptions($this->queries, $this->store, $this, NoticeSelection::create([$scope]), $tools, $resources, $lifetime, 10000, $frameBytes, $prompts);
        $availability = new class implements McpToolAvailability, McpResourceAvailability {
            public function isAvailable(McpToolInfo|McpResourceInfo $item): bool { return true; }
        };
        $toolRegistry = new McpToolRegistry([new RecoverNoticesTool($this->queries)]);
        $this->visiblePrompt = new PromptProvider(McpPromptInfo::fromArray(['name' => 'review', 'arguments' => [['name' => 'note']]]));
        $this->visiblePrompt->output = McpPromptOutput::fromArray(['messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Safe review']]]]);
        $this->hiddenPrompt = new PromptProvider(McpPromptInfo::fromArray(['name' => 'CONCEALED', 'description' => 'PRIVATE DEFINITION']));
        $promptAccess = new class($this) implements McpPromptAvailability {
            public function __construct(private SubscriptionFixture $fixture) {}
            public function isAvailable(McpPromptInfo $prompt): bool { return $this->fixture->promptPermitted && $prompt->name() !== 'CONCEALED' && ($this->fixture->promptPolicy === null || ($this->fixture->promptPolicy)()); }
        };
        $this->registry = new McpCapabilityRegistry(new McpServerInfo('Live fixture', '1'), [
            new McpToolDiscovery($toolRegistry, $availability, str_repeat('key', 16)),
            new McpToolInvocation(new McpToolInvoker($toolRegistry, $availability)),
            new McpResourceDiscovery([], $availability, str_repeat('key', 16), 'live'),
            ...($servePrompts ? [new McpPromptDiscovery([$this->visiblePrompt, $this->hiddenPrompt], $promptAccess, str_repeat('prompt', 8), 'live')] : [])
        ], $this->subscriptions);
        $factory = new HttpFactory();
        $guard = new class($this) implements McpInvocationGuard {
            public function __construct(private SubscriptionFixture $fixture) {}
            public function allows(string $method, ?string $name): bool { return $this->fixture->admit; }
        };
        $this->endpoint = new McpRequestHandler($this->registry, new ExactMcpOriginPolicy(['https://client.test']), $guard, new McpResponseFactory($factory, $factory, $this));
    }

    public function subscriber(): string { return 'subscriber-'.$this->scope; }
    public function allows(string $subscriber, string $filter, ?string $uri, ?Notice $notice): bool { return $this->permitted && ($uri === null || $uri === 'file:///allowed') && ($this->policy === null || ($this->policy)($filter, $uri, $notice)); }
    public function currentAccess(NoticeSelection $selection): NoticeAccess { if ($this->queryPolicy !== null) { ($this->queryPolicy)(); } return NoticeRecoveryFixture::access([$this->scope], caller: $this->subscriber()); }
    public function record(Throwable $failure): void { $this->diagnostics[] = $failure::class; }

    public static function request(array $notifications = ['toolsListChanged' => true], int|string $id = 7): ServerRequest
    {
        return new ServerRequest('POST', 'http://localhost/mcp', ['Origin' => 'https://client.test', 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'subscriptions/listen'], json_encode([
            'jsonrpc' => '2.0', 'id' => $id, 'method' => 'subscriptions/listen', 'params' => ['notifications' => (object) $notifications, '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]
        ], JSON_THROW_ON_ERROR));
    }
}
