# Observability

This library provides a first-class observability layer as application-layer ports with pluggable adapter implementations. The three pillars are **health checks**, **metrics**, and **audit logging**. A fourth concern — **HMAC-secured AI operations** — is documented in its own section below.

---

## Health Checks

### What a `HealthReport` looks like

```json
{
  "status": "degraded",
  "timestamp": "2026-05-25T14:00:00+00:00",
  "checks": [
    { "name": "database",    "status": "healthy",   "message": "ping 3ms",               "context": {} },
    { "name": "queue",       "status": "degraded",  "message": "depth 4200 (limit 1000)", "context": {} },
    { "name": "payment-api", "status": "unhealthy", "message": "connection refused",       "context": {} }
  ]
}
```

`overall` = worst of all individual check statuses. An AI agent, CI job, or deployment script can use `isHealthy()` as a gate before making changes.

### Interfaces

```
Application\Observability\HealthCheck       — a single check
Application\Observability\HealthAggregator — aggregates N checks → HealthReport
```

### Built-in adapters

| Adapter | Package required |
|---------|-----------------|
| `HealthReporter` | — |
| `DatabaseHealthCheck` | `doctrine/dbal` |
| `HttpEndpointHealthCheck` | any `HttpClient` adapter |

### Wiring (no framework)

```php
use Fight\Common\Adapter\Observability\Health\HealthReporter;
use Fight\Common\Adapter\Observability\Health\DatabaseHealthCheck;
use Fight\Common\Adapter\Observability\Health\HttpEndpointHealthCheck;

$reporter = new HealthReporter();
$reporter->addCheck(new DatabaseHealthCheck($connection));
$reporter->addCheck(new DatabaseHealthCheck($replicaConnection, 'database-replica'));
$reporter->addCheck(new HttpEndpointHealthCheck($httpClient, $factory, 'https://pay.example.com/ping', 'payment-api'));

$report = $reporter->report();          // HealthReport
$report->isHealthy();                   // bool
$report->overall()->toString();         // 'healthy' | 'degraded' | 'unhealthy'
json_encode($report);                   // structured JSON above
```

### Wiring (Symfony DI)

```yaml
# config/services.yaml
services:
  Fight\Common\Application\Observability\HealthAggregator:
    class: Fight\Common\Adapter\Observability\Health\HealthReporter

  Fight\Common\Adapter\Observability\Health\DatabaseHealthCheck:
    arguments: ['@doctrine.dbal.default_connection']
    tags: ['app.health_check']

  Fight\Common\Adapter\Observability\Health\HttpEndpointHealthCheck:
    arguments:
      - '@Fight\Common\Application\HttpClient\Transport\HttpClient'
      - '@Fight\Common\Application\HttpClient\Message\MessageFactory'
      - 'https://pay.example.com/ping'
      - 'payment-api'
    tags: ['app.health_check']
```

Then wire all tagged checks into the aggregator:

```php
// In a compiler pass
$aggregator = $container->findDefinition(HealthAggregator::class);
foreach ($container->findTaggedServiceIds('app.health_check') as $id => $_) {
    $aggregator->addMethodCall('addCheck', [new Reference($id)]);
}
```

### Implementing a custom check

```php
use Fight\Common\Application\Observability\HealthCheck;
use Fight\Common\Domain\Observability\HealthResult;
use Fight\Common\Domain\Observability\HealthStatus;

final class RedisHealthCheck implements HealthCheck
{
    public function __construct(private \Redis $redis) {}

    public function name(): string { return 'redis'; }

    public function check(): HealthResult
    {
        try {
            $start = hrtime(true);
            $this->redis->ping();
            $ms = round((hrtime(true) - $start) / 1e6, 2);
            return new HealthResult('redis', HealthStatus::healthy(), "ping {$ms}ms");
        } catch (\Throwable $e) {
            return new HealthResult('redis', HealthStatus::unhealthy(), $e->getMessage());
        }
    }
}
```

---

## Metrics

### What metrics look like

The bus filters auto-emit these metrics without modifying any handler code:

| Metric | Type | Tags | Tells you |
|--------|------|------|-----------|
| `command.executed` | counter | `type=PlaceOrderCommand` | throughput per command type |
| `command.failed` | counter | `type=..., exception=ValidationException` | error rate + failure mode |
| `command.latency_ms` | histogram | `type=PlaceOrderCommand` | p50/p95/p99 per handler |
| `query.executed` | counter | `type=FindOrdersQuery` | read-path throughput |
| `query.failed` | counter | `type=..., exception=...` | query error rate |
| `query.latency_ms` | histogram | `type=FindOrdersQuery` | query latency distribution |

### Interface

```
Application\Observability\MetricsCollector
    increment(string $metric, array $tags = []): void
    gauge(string $metric, float $value, array $tags = []): void
    histogram(string $metric, float $value, array $tags = []): void
```

### Built-in adapters

| Adapter | Notes |
|---------|-------|
| `NullMetricsCollector` | no-op; **default** — zero overhead |
| `StatsDMetricsCollector` | UDP DogStatsD; requires `ext-sockets` |

### Wiring the StatsD adapter

```php
use Fight\Common\Adapter\Observability\Metrics\StatsDMetricsCollector;

$metrics = new StatsDMetricsCollector(
    host: 'statsd.internal',
    port: 8125,
    prefix: 'myapp'
);
```

The StatsD adapter emits [DogStatsD](https://docs.datadoghq.com/developers/dogstatsd/) wire format:

```
myapp.command.executed:1|c|#type:PlaceOrderCommand
myapp.command.latency_ms:42.3|ms|#type:PlaceOrderCommand
myapp.query.failed:1|c|#type:FindOrdersQuery,exception:NotFoundException
```

### Adding bus middleware

Add `MetricsCommandFilter` and `MetricsQueryFilter` to your pipelines. They wrap `$next($message)` in a try/catch and emit on both success and failure paths.

```php
use Fight\Common\Adapter\Messaging\Command\MetricsCommandFilter;
use Fight\Common\Adapter\Messaging\Query\MetricsQueryFilter;

// Command pipeline
$commandPipeline = new CommandPipeline($routingBus);
$commandPipeline->addFilter(new MetricsCommandFilter($metrics));

// Query pipeline
$queryPipeline = new QueryPipeline($routingBus);
$queryPipeline->addFilter(new MetricsQueryFilter($metrics));
```

Symfony DI:

```yaml
Fight\Common\Adapter\Messaging\Command\MetricsCommandFilter:
  arguments: ['@Fight\Common\Application\Observability\MetricsCollector']
  tags: ['app.command_filter']

Fight\Common\Adapter\Messaging\Query\MetricsQueryFilter:
  arguments: ['@Fight\Common\Application\Observability\MetricsCollector']
  tags: ['app.query_filter']
```

### Implementing a custom collector

Implement `MetricsCollector` and forward to any backend — Prometheus, InfluxDB, CloudWatch, etc.:

```php
final class PrometheusMetricsCollector implements MetricsCollector
{
    public function __construct(private CollectorRegistry $registry, private string $ns = '') {}

    public function increment(string $metric, array $tags = []): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            $this->ns, str_replace('.', '_', $metric), $metric, array_keys($tags)
        );
        $counter->incBy(1, array_values($tags));
    }

    public function gauge(string $metric, float $value, array $tags = []): void { /* ... */ }
    public function histogram(string $metric, float $value, array $tags = []): void { /* ... */ }
}
```

---

## Audit Log

### What an `AuditEntry` looks like

```json
{
  "id": "019f1a2b-3c4d-7e8f-9a0b-c1d2e3f4a5b6",
  "actor": "user:42",
  "action": "order.placed",
  "timestamp": "2026-05-25T14:01:23+00:00",
  "context": {
    "order_id": "ORD-9871",
    "amount": 129.99,
    "ip": "203.0.113.4"
  }
}
```

Unlike PSR-3 debug logs, audit entries are **business facts** — meaningful to domain experts, compliance teams, and AI agents diagnosing system state.

### Interface

```
Application\Observability\AuditLog
    record(AuditEntry $entry): void
```

### Repository (for queryable storage)

```
Application\Repository\AuditRepository
    save(AuditEntry $entry): void
    findByActor(string $actor, Pagination $pagination): ResultSet
    findByAction(string $action, Pagination $pagination): ResultSet
    findBetween(DateTimeImmutable $from, DateTimeImmutable $to, Pagination $pagination): ResultSet
```

### Built-in adapters

| Adapter | Notes |
|---------|-------|
| `NullAuditLog` | no-op; **default** |
| `LoggingAuditLog` | writes to PSR-3 as structured JSON; good for development |

### Recording entries

```php
use Fight\Common\Domain\Observability\AuditEntry;

// In a command handler or service
$entry = AuditEntry::record(
    actor: "user:{$user->id()}",
    action: 'order.placed',
    context: [
        'order_id' => $order->id()->toString(),
        'amount'   => $order->total(),
    ]
);

$auditLog->record($entry);
```

### Wiring

```php
use Fight\Common\Adapter\Observability\Audit\LoggingAuditLog;
use Psr\Log\LogLevel;

// Development: log to PSR-3
$auditLog = new LoggingAuditLog($logger, LogLevel::INFO);

// Production: implement AuditLog backed by AuditRepository
$auditLog = new class($auditRepository) implements AuditLog {
    public function __construct(private AuditRepository $repo) {}
    public function record(AuditEntry $entry): void {
        $this->repo->save($entry);
    }
};
```

---

## HMAC-Secured AI Operations

> **Note:** The `WebhookDispatcher`, `HmacWebhookDispatcher`, and `AiOperation` classes are
> deprecated since 1.2 and will be removed in 2.0. MCP/AI operation tooling will be redesigned
> as a future feature with proper architectural boundaries. The HMAC authentication layer
> (`HmacAuthenticator`, `HmacRequestService`) remains unaffected.

This builds on the existing `HmacAuthenticator` / `HmacRequestService` to enable AI agents to safely trigger production operations over HTTP without SSH or direct shell access.

### The request format

The AI agent sends a signed HTTP POST:

```
POST /ops HTTP/1.1
Content-Type: application/json
Authorization: HMAC <public-key>
Credential: <public-key>
Signature: <hmac-sha256-hex>
X-Timestamp: 1748000000
X-Nonce: a3f8b1c2d4e5f607

{"action": "health_check", "payload": {}}
```

**Known actions**: `health_check`, `clear_cache`, `run_migration`, `deploy`

The server side:
1. `HmacAuthenticator::validate()` — verifies headers (timestamp tolerance, credential, content hash, signature)
2. Optionally consumes the nonce via `NonceRepository` to prevent replay within the tolerance window
3. Decodes the JSON body and validates the action name against known operations
4. Executes the action

### Replay prevention (Nonce tracking)

The `HmacAuthenticator` accepts an optional `NonceRepository`. When provided, every validated nonce is consumed — a second request with the same nonce within the timestamp tolerance window throws `AuthException`.

```
Application\Auth\NonceRepository
    consume(Nonce $nonce): void   — throws AuthException if already consumed
    purgeExpired(): void          — removes nonces past their TTL
```

| Adapter | Notes |
|---------|-------|
| `InMemoryNonceRepository` | single-process; good for testing |
| `DoctrineNonceRepository` | `hmac_nonces` table; atomic via unique constraint |

#### `hmac_nonces` schema

```sql
CREATE TABLE hmac_nonces (
    nonce      VARCHAR(64)  NOT NULL PRIMARY KEY,
    expires_at DATETIME     NOT NULL
);
```

### Wiring `HmacAuthenticator` with nonce replay prevention

```php
use Fight\Common\Adapter\Auth\Hmac\HmacAuthenticator;
use Fight\Common\Adapter\Auth\Nonce\DoctrineNonceRepository;

$nonces = new DoctrineNonceRepository($connection);

// Purge expired nonces periodically (e.g., cron, middleware)
$nonces->purgeExpired();

$authenticator = new HmacAuthenticator(
    public: $publicKey,
    private: $privateKeyHex,
    timeTolerance: 300,      // seconds; nonce TTL matches this window
    nonces: $nonces
);

// In a controller / middleware
$authenticator->validate($request);  // throws AuthException on any failure
```

> **Breaking change from v1.0:** `validate()` now always throws `AuthException` instead of returning `false` on signature mismatch. Callers that checked `if (!$authenticator->validate($request))` should now rely on catching `AuthException`.

### Dispatching signed webhook payloads

```
Application\Auth\WebhookDispatcher
    dispatch(string $url, string $action, array $payload = []): void
```

```php
use Fight\Common\Adapter\Auth\Hmac\HmacWebhookDispatcher;

$dispatcher = new HmacWebhookDispatcher(
    client:  $httpClient,
    factory: $messageFactory,
    signer:  new HmacRequestService($publicKey, $privateKeyHex)
);

// Send a signed "deploy" operation to a remote agent listener
$dispatcher->dispatch('https://prod.example.com/ops', 'deploy', ['version' => '1.4.2']);
```

The `WebhookDispatcher` builds the JSON body, signs the HTTP request via `HmacRequestService`, and sends it. The receiving end uses `HmacAuthenticator` to verify.

### Parsing and validating incoming operations

```php
// After HmacAuthenticator::validate() passes:
$data = json_decode((string) $request->getBody(), true);
$action = $data['action'] ?? null;
$payload = $data['payload'] ?? [];

match ($action) {
    'health_check'  => $response->setBody(json_encode($reporter->report())),
    'clear_cache'   => $cache->clear(),
    'run_migration' => $migrator->run($payload['name']),
    'deploy'        => $deployer->deploy($payload['version']),
};
```

### Generating keys

Use `HmacKeyGenerator` to create key pairs:

```php
use Fight\Common\Adapter\Auth\Hmac\HmacKeyGenerator;

$publicKey  = HmacKeyGenerator::generateSecureRandom(16); // 32 hex chars
$privateKey = HmacKeyGenerator::generateSecureRandom(32); // 64 hex chars
```

Store the private key securely (environment variable, secrets manager). Share the public key with the consuming system as the credential identifier.

---

## Exposing health and audit data to AI agents

The recommended pattern:

1. Register a secured `/ops` endpoint protected by `HmacAuthenticator` + `DoctrineNonceRepository`
2. Handle `health_check` actions by returning `json_encode($reporter->report())`
3. Handle audit queries by returning results from `AuditRepository::findBetween()`

The AI agent can then:
- Check system health before making changes (`health_check`)
- Query the audit log to understand what happened before an anomaly
- Trigger controlled operations (`clear_cache`, `deploy`) with full auditability

All operations should be recorded to `AuditLog` — including the AI agent's own requests — using `"system:ai-agent"` as the actor.
