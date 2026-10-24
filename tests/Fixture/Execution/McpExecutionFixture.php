<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Execution;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Execution\Dbal\DbalActivityReader;
use Fight\Common\Adapter\Execution\Dbal\DbalExecutionReader;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Execution\Dbal\DbalPhaseReader;
use Fight\Common\Adapter\Execution\Dbal\DbalPhaseRepository;
use Fight\Common\Adapter\Execution\Dbal\DbalProtectedContentRepository;
use Fight\Common\Adapter\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Execution\Dbal\DbalSessionRepository;
use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Execution\Observation\CommandHandler\RecordPhaseObservationHandler;
use Fight\Common\Application\Execution\Observation\CommandHandler\RecordProtectedDetailHandler;
use Fight\Common\Application\Execution\Observation\CommandHandler\RecordSessionObservationHandler;
use Fight\Common\Application\Execution\Observation\QueryHandler\ListPhaseActivityHandler;
use Fight\Common\Application\Execution\Observation\Service\ObservationRecorder;
use Fight\Common\Application\Execution\ObservationAdmission;
use Fight\Common\Application\Execution\Phase\QueryHandler\ListExecutionPhasesHandler;
use Fight\Common\Application\Execution\Session\QueryHandler\GetExecutionHandler;
use Fight\Common\Application\Execution\Session\QueryHandler\ListExecutionsHandler;
use Fight\Common\Application\Execution\Session\QueryHandler\SummarizeExecutionsHandler;
use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Application\Execution\Session\Service\ExecutionReadAdmission;
use Fight\Common\Application\Mcp\Execution\GetExecutionTool;
use Fight\Common\Application\Mcp\Execution\ListExecutionPhasesTool;
use Fight\Common\Application\Mcp\Execution\ListExecutionsTool;
use Fight\Common\Application\Mcp\Execution\ListPhaseActivityTool;
use Fight\Common\Application\Mcp\Execution\SummarizeExecutionsTool;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Observation\Command\RecordPhaseObservation;
use Fight\Common\Domain\Execution\Observation\Command\RecordProtectedDetail;
use Fight\Common\Domain\Execution\Observation\Command\RecordSessionObservation;
use Fight\Common\Domain\Execution\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Utility\ClassName;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use RuntimeException;
use Throwable;

final class McpExecutionFixture implements ExecutionReadAdmission, ObservationAdmission, McpToolAvailability, McpInvocationGuard, McpDiagnostics
{
    public Connection $db;
    public RoutingQueryBus $queries;
    public ExecutionAccess $access;
    public bool $available = true;
    public bool $guard = true;
    public int $admissionCalls = 0;
    public int $events = 0;
    public array $diagnostics = [];
    public McpRequestHandler $endpoint;
    private RoutingCommandBus $commands;

    public function __construct(string $backend)
    {
        $params = ['driver' => 'pdo_sqlite', 'memory' => true];
        if ($backend !== 'sqlite') {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            if (!is_string($dsn) || $dsn === '') { throw new RuntimeException('Real disposable '.$backend.' is required, not skipped'); }
            $params = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql'])->parse($dsn);
        }
        $this->db = DriverManager::getConnection($params);
        if ($backend === 'sqlite') { $this->db->executeStatement('PRAGMA foreign_keys = ON'); }
        foreach (array_reverse(DbalObservationSchema::TABLES) as $table) { $this->db->executeStatement('DROP TABLE IF EXISTS '.$table); }
        new DbalObservationSchema()->install($this->db);
        $transaction = new DbalRecordingTransaction($this->db);
        $events = new SimpleEventDispatcher();
        $events->addHandler(ClassName::underscore(ObservationRecorded::class), function (): void { ++$this->events; });
        $recorder = new ObservationRecorder($transaction, new DbalSessionRepository($transaction), new DbalPhaseRepository($transaction), new DbalObservationRepository($transaction), $this, $events, new DbalProtectedContentRepository($transaction));
        $router = new InMemoryCommandRouter();
        foreach ([new RecordSessionObservationHandler($recorder), new RecordPhaseObservationHandler($recorder), new RecordProtectedDetailHandler($recorder)] as $handler) { $router->registerHandler($handler::commandRegistration(), $handler); }
        $this->commands = new RoutingCommandBus($router);
        $this->access = ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]);
        $reader = new DbalExecutionReader($this->db);
        $router = new InMemoryQueryRouter();
        foreach ([new SummarizeExecutionsHandler($this, $reader), new ListExecutionsHandler($this, $reader), new GetExecutionHandler($this, $reader), new ListExecutionPhasesHandler($this, new DbalPhaseReader($this->db)), new ListPhaseActivityHandler($this, new DbalActivityReader($this->db))] as $handler) { $router->registerHandler($handler::queryRegistration(), $handler); }
        $this->queries = new RoutingQueryBus($router);
        $tools = new McpToolRegistry([new SummarizeExecutionsTool($this->queries), new ListExecutionsTool($this->queries), new GetExecutionTool($this->queries), new ListExecutionPhasesTool($this->queries), new ListPhaseActivityTool($this->queries)]);
        $capabilities = new McpCapabilityRegistry(new McpServerInfo('Execution fixture', '1'), [new McpToolDiscovery($tools, $this, str_repeat('catalog-key-', 4), pageSize: 2), new McpToolInvocation(new McpToolInvoker($tools, $this))]);
        $factory = new HttpFactory();
        $this->endpoint = new McpRequestHandler($capabilities, new ExactMcpOriginPolicy(['https://client.test']), $this, new McpResponseFactory($factory, $factory, $this));
    }

    public function admit(Observation $observation): void {}
    public function currentAccess(ExecutionSelection $selection): ExecutionAccess { ++$this->admissionCalls; return $this->access; }
    public function isAvailable(McpToolInfo $tool): bool { return $this->available; }
    public function allows(string $method, ?string $name): bool { return $this->guard; }
    public function record(Throwable $failure): void { $this->diagnostics[] = 'sanitized_failure'; }

    public function seed(Observation $observation, ?ProtectedContent $content = null): void
    {
        if ($content !== null) { $command = new RecordProtectedDetail($observation, $content); }
        elseif ($observation->phase() === null) { $command = new RecordSessionObservation($observation); }
        else { $command = new RecordPhaseObservation($observation); }
        $this->commands->execute($command);
    }

    public function call(string $name, array $arguments): array
    {
        return $this->request('tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
    }

    public function request(string $method, array $params = [], array $headers = []): array
    {
        $headers += ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method, 'Origin' => 'https://client.test'];
        if ($method === 'tools/call') { $headers += ['Mcp-Name' => $params['name']]; }
        $body = json_encode(['jsonrpc' => '2.0', 'id' => 'inspection-雪', 'method' => $method, 'params' => $params + ['_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR);
        $response = $this->endpoint->handle(new ServerRequest('POST', 'https://server.test/consumer/mcp', $headers, $body));
        return ['status' => $response->getStatusCode(), 'body' => json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)];
    }

    public function state(): array
    {
        return array_map(fn(string $table): array => $this->db->fetchAllAssociative('SELECT * FROM '.$table), DbalObservationSchema::TABLES);
    }
}
