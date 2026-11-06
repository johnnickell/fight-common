<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp\Execution;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Http\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Mcp\Http\McpRequestHandler;
use Fight\Common\Adapter\Mcp\Http\McpResponseFactory;
use Fight\Common\Adapter\Mcp\Persistence\Detail\Dbal\DbalDetailReader;
use Fight\Common\Adapter\Mcp\Persistence\Detail\Dbal\DbalProtectedContentRepository;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationTables;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Mcp\Persistence\Observation\Dbal\DbalActivityReader;
use Fight\Common\Adapter\Mcp\Persistence\Observation\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Mcp\Persistence\Phase\Dbal\DbalPhaseReader;
use Fight\Common\Adapter\Mcp\Persistence\Phase\Dbal\DbalPhaseRepository;
use Fight\Common\Adapter\Mcp\Persistence\Session\Dbal\DbalExecutionReader;
use Fight\Common\Adapter\Mcp\Persistence\Session\Dbal\DbalSessionRepository;
use Fight\Common\Adapter\Mcp\Tool\Detail\GetCapturedDetailTool;
use Fight\Common\Adapter\Mcp\Tool\Detail\ListCapturedMessagesTool;
use Fight\Common\Adapter\Mcp\Tool\Observation\ListPhaseActivityTool;
use Fight\Common\Adapter\Mcp\Tool\Phase\ListExecutionPhasesTool;
use Fight\Common\Adapter\Mcp\Tool\Session\GetExecutionTool;
use Fight\Common\Adapter\Mcp\Tool\Session\ListExecutionsTool;
use Fight\Common\Adapter\Mcp\Tool\Session\SummarizeExecutionsTool;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Common\Application\Mcp\Capability\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\Detail\CommandHandler\RemoveCapturedDetailHandler;
use Fight\Common\Application\Mcp\Detail\Exception\DetailRemovalRejected;
use Fight\Common\Application\Mcp\Detail\QueryHandler\GetCapturedDetailHandler;
use Fight\Common\Application\Mcp\Detail\QueryHandler\ListCapturedMessagesHandler;
use Fight\Common\Application\Mcp\Detail\Service\DetailAccess;
use Fight\Common\Application\Mcp\Detail\Service\DetailReadAdmission;
use Fight\Common\Application\Mcp\Detail\Service\DetailRemovalAdmission;
use Fight\Common\Application\Mcp\Detail\Service\DetailRemover;
use Fight\Common\Application\Mcp\Http\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\Observation\CommandHandler\RecordPhaseObservationHandler;
use Fight\Common\Application\Mcp\Observation\CommandHandler\RecordProtectedDetailHandler;
use Fight\Common\Application\Mcp\Observation\CommandHandler\RecordSessionObservationHandler;
use Fight\Common\Application\Mcp\Observation\QueryHandler\ListPhaseActivityHandler;
use Fight\Common\Application\Mcp\Observation\Service\ObservationAdmission;
use Fight\Common\Application\Mcp\Observation\Service\ObservationRecorder;
use Fight\Common\Application\Mcp\Phase\QueryHandler\ListExecutionPhasesHandler;
use Fight\Common\Application\Mcp\Protocol\McpServerInfo;
use Fight\Common\Application\Mcp\Session\QueryHandler\GetExecutionHandler;
use Fight\Common\Application\Mcp\Session\QueryHandler\ListExecutionsHandler;
use Fight\Common\Application\Mcp\Session\QueryHandler\SummarizeExecutionsHandler;
use Fight\Common\Application\Mcp\Session\Service\ExecutionAccess;
use Fight\Common\Application\Mcp\Session\Service\ExecutionReadAdmission;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Domain\Mcp\Detail\Command\RemoveCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Observation\Command\RecordPhaseObservation;
use Fight\Common\Domain\Mcp\Observation\Command\RecordProtectedDetail;
use Fight\Common\Domain\Mcp\Observation\Command\RecordSessionObservation;
use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Utility\ClassName;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use RuntimeException;
use Throwable;

final class McpExecutionFixture implements ExecutionReadAdmission, ObservationAdmission, McpToolAvailability, McpInvocationGuard, McpDiagnostics
{
    public Connection $connection;
    public RoutingQueryBus $queryBus;
    public ExecutionAccess $access;
    public DetailAccess $detailAccess;
    public int $detailAdmissionCalls = 0;
    public int $removalAdmissionCalls = 0;
    public bool $removalAllowed = false;
    public int $commandCalls = 0;
    public array $availabilitySequence = [];
    public bool $available = true;
    public bool $guard = true;
    public int $admissionCalls = 0;
    public int $eventCount = 0;
    public array $diagnostics = [];
    public McpRequestHandler $endpoint;
    public string $lastResponse = '';
    public string $requestId = 'inspection-雪';
    private RoutingCommandBus $commandBus;

    public function __construct(string $backend, bool $protected = false, string $serverName = 'Execution fixture', public readonly DbalObservationTables $tables = new DbalObservationTables())
    {
        $params = ['driver' => 'pdo_sqlite', 'memory' => true];
        if ($backend !== 'sqlite') {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            if (!is_string($dsn) || $dsn === '') { throw new RuntimeException('Real disposable '.$backend.' is required, not skipped'); }
            $params = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql'])->parse($dsn);
        }
        $this->connection = DriverManager::getConnection($params);
        if ($backend === 'sqlite') { $this->connection->executeStatement('PRAGMA foreign_keys = ON'); }
        foreach (array_reverse($this->selectedTables()) as $table) { $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table->quoted($this->connection->getDatabasePlatform())); }
        new DbalObservationSchema($tables)->install($this->connection);
        $recordingTransaction = new DbalRecordingTransaction($this->connection, $tables);
        $eventDispatcher = new SimpleEventDispatcher();
        $eventDispatcher->addHandler(ClassName::underscore(ObservationRecorded::class), function (): void { ++$this->eventCount; });
        $observationRepository = new DbalObservationRepository($recordingTransaction);
        $protectedContentRepository = new DbalProtectedContentRepository($recordingTransaction);
        $observationRecorder = new ObservationRecorder($recordingTransaction, new DbalSessionRepository($recordingTransaction), new DbalPhaseRepository($recordingTransaction), $observationRepository, $this, $eventDispatcher, $protectedContentRepository);
        $removalAdmission = new class($this) implements DetailRemovalAdmission {
            public function __construct(private McpExecutionFixture $fixture) {}
            public function admit(RemoveCapturedDetail $command): void {
                ++$this->fixture->removalAdmissionCalls;
                if (!$this->fixture->removalAllowed) { throw new DetailRemovalRejected(); }
            }
        };
        $remover = new DetailRemover($recordingTransaction, $observationRepository, $protectedContentRepository, $removalAdmission);
        $commandRouter = new InMemoryCommandRouter();
        foreach ([new RecordSessionObservationHandler($observationRecorder), new RecordPhaseObservationHandler($observationRecorder), new RecordProtectedDetailHandler($observationRecorder), new RemoveCapturedDetailHandler($remover)] as $handler) { $commandRouter->registerHandler($handler::commandRegistration(), $handler); }
        $this->commandBus = new RoutingCommandBus($commandRouter);
        $this->access = ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]);
        $executionReader = new DbalExecutionReader($this->connection, $tables);
        $queryRouter = new InMemoryQueryRouter();
        foreach ([new SummarizeExecutionsHandler($this, $executionReader), new ListExecutionsHandler($this, $executionReader), new GetExecutionHandler($this, $executionReader), new ListExecutionPhasesHandler($this, new DbalPhaseReader($this->connection, $tables)), new ListPhaseActivityHandler($this, new DbalActivityReader($this->connection, $tables))] as $handler) { $queryRouter->registerHandler($handler::queryRegistration(), $handler); }
        $this->queryBus = new RoutingQueryBus($queryRouter);
        $this->detailAccess = DetailAccess::create(['project' => null], metadataGrants: ['project' => null], bodyGrants: ['project' => null]);
        $detailReadAdmission = new class($this) implements DetailReadAdmission {
            public function __construct(private McpExecutionFixture $fixture) {}
            public function currentAccess(DetailSelection $selection): DetailAccess {
                ++$this->fixture->detailAdmissionCalls;
                return $this->fixture->detailAccess;
            }
        };
        $detailReader = new DbalDetailReader($this->connection, $tables);
        foreach ([new GetCapturedDetailHandler($detailReadAdmission, $detailReader), new ListCapturedMessagesHandler($detailReadAdmission, $detailReader)] as $handler) { $queryRouter->registerHandler($handler::queryRegistration(), $handler); }
        $registered = [new SummarizeExecutionsTool($this->queryBus), new ListExecutionsTool($this->queryBus), new GetExecutionTool($this->queryBus), new ListExecutionPhasesTool($this->queryBus), new ListPhaseActivityTool($this->queryBus)];
        if ($protected) { $registered[] = new ListCapturedMessagesTool($this->queryBus); $registered[] = new GetCapturedDetailTool($this->queryBus); }
        $toolRegistry = new McpToolRegistry($registered);
        $capabilityRegistry = new McpCapabilityRegistry(new McpServerInfo($serverName, '1'), [new McpToolDiscovery($toolRegistry, $this, str_repeat('catalog-key-', 4), pageSize: 2), new McpToolInvocation(new McpToolInvoker($toolRegistry, $this))]);
        $httpFactory = new HttpFactory();
        $this->endpoint = new McpRequestHandler($capabilityRegistry, new ExactMcpOriginPolicy(['https://client.test']), $this, new McpResponseFactory($httpFactory, $httpFactory, $this));
    }

    public function admit(Observation $observation): void {}
    public function currentAccess(ExecutionSelection $selection): ExecutionAccess { ++$this->admissionCalls; return $this->access; }
    public function isAvailable(McpToolInfo $tool): bool { return $this->availabilitySequence !== [] ? array_shift($this->availabilitySequence) : $this->available; }
    public function allows(string $method, ?string $name): bool { return $this->guard; }
    public function record(Throwable $failure): void { $this->diagnostics[] = 'sanitized_failure'; }

    public function seed(Observation $observation, ?ProtectedContent $content = null): void
    {
        if ($content !== null) { $command = new RecordProtectedDetail($observation, $content); }
        elseif ($observation->phase() === null) { $command = new RecordSessionObservation($observation); }
        else { $command = new RecordPhaseObservation($observation); }
        ++$this->commandCalls;
        $this->commandBus->execute($command);
    }

    public function remove(RemoveCapturedDetail $command): void
    {
        ++$this->commandCalls;
        $this->commandBus->execute($command);
    }

    public function call(string $name, array $arguments): array
    {
        return $this->request('tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
    }

    public function request(string $method, array $params = [], array $headers = []): array
    {
        $headers += ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method, 'Origin' => 'https://client.test'];
        if ($method === 'tools/call') { $headers += ['Mcp-Name' => $params['name']]; }
        $body = json_encode(['jsonrpc' => '2.0', 'id' => $this->requestId, 'method' => $method, 'params' => $params + ['_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR);
        $response = $this->endpoint->handle(new ServerRequest('POST', 'https://server.test/consumer/mcp', $headers, $body));
        $this->lastResponse = (string) $response->getBody();
        return ['status' => $response->getStatusCode(), 'body' => json_decode($this->lastResponse, true, flags: JSON_THROW_ON_ERROR)];
    }

    public function state(): array
    {
        return array_map(fn(DbalTableName $table): array => $this->connection->fetchAllAssociative('SELECT * FROM '.$table->quoted($this->connection->getDatabasePlatform())), $this->selectedTables());
    }

    private function selectedTables(): array
    {
        return [$this->tables->schemaTable(), $this->tables->sessionTable(), $this->tables->phaseTable(), $this->tables->observationTable(), $this->tables->contentTable()];
    }
}
