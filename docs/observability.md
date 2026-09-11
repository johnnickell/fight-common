Fight Common separates three observability signals behind narrow Application contracts:
**health checks** report current dependency state, **metrics** record measurements over time,
and **audit logging** preserves business-relevant facts. Provider adapters live at the boundary;
the application decides what to collect, expose, retain, and alert on.

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

The bus filters auto-emit these metrics without modifying any handler code. `type` and `exception`
contain fully qualified class names; the examples below use representative application namespaces:

| Metric | Type | Tags | Tells you |
|--------|------|------|-----------|
| `command.executed` | counter | `type=App\Domain\Order\PlaceOrder` | throughput per command type |
| `command.failed` | counter | `type=..., exception=App\Application\Order\ValidationException` | error rate + failure mode |
| `command.latency_ms` | histogram | `type=App\Domain\Order\PlaceOrder` | p50/p95/p99 per handler |
| `query.executed` | counter | `type=App\Domain\Order\FindOrders` | read-path throughput |
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
| `NullMetricsCollector` | deliberate no-op when metrics are disabled |
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
myapp.command.executed:1|c|#type:App\Domain\Order\PlaceOrder
myapp.command.latency_ms:42.3|ms|#type:App\Domain\Order\PlaceOrder
myapp.query.failed:1|c|#type:App\Domain\Order\FindOrders,exception:App\Domain\Order\OrderNotFound
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
Domain\Observability\AuditRepository
    add(AuditEntry $entry): void
    getByActor(string $actor, Pagination $pagination): ResultSet
    getByAction(string $action, Pagination $pagination): ResultSet
    getBetween(DateTimeImmutable $from, DateTimeImmutable $to, Pagination $pagination): ResultSet
```

### Built-in adapters

| Adapter | Notes |
|---------|-------|
| `NullAuditLog` | deliberate no-op when audit recording is disabled |
| `LoggingAuditLog` | logs the message `audit` with `AuditEntry::toArray()` as PSR-3 context |

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
use Fight\Common\Application\Observability\AuditLog;
use Fight\Common\Domain\Observability\AuditEntry;
use Fight\Common\Domain\Observability\AuditRepository;
use Psr\Log\LogLevel;

// Development: log to PSR-3
$auditLog = new LoggingAuditLog($logger, LogLevel::INFO);

// Production: implement AuditLog backed by AuditRepository
$auditLog = new class($auditRepository) implements AuditLog {
    public function __construct(private AuditRepository $repo) {}
    public function record(AuditEntry $entry): void {
        $this->repo->add($entry);
    }
};
```

---

## Provider selection

Choose each provider independently at the composition root. An application can, for example, use
database and HTTP health checks, send metrics to StatsD, and persist audit entries through its own
`AuditLog` implementation. Fight Common does not install a global observability provider or expose
an HTTP endpoint automatically.

| Signal | Portable contract | Supplied provider choices | Consumer-owned policy |
|---|---|---|---|
| Health | `HealthCheck`, `HealthAggregator` | `HealthReporter`, database and HTTP checks | Readiness/liveness meaning, endpoint status code, timeout, authentication, and exposure |
| Metrics | `MetricsCollector` | null collector or StatsD/DogStatsD over UDP | Names, tags, cardinality, sampling, retention, dashboards, and alerts |
| Audit | `AuditLog` | null or PSR-3 logging sink | Required business events, durable storage, querying, access, retention, and deletion |

Use the null adapters deliberately when a signal is disabled. They prevent conditional branches in
application code, but they do not preserve evidence and should not be mistaken for configured
monitoring or audit storage.

## Failure behavior

- `DatabaseHealthCheck` and `HttpEndpointHealthCheck` catch provider failures and convert them to
  unhealthy `HealthResult` values. `HealthReporter` itself does not catch exceptions from arbitrary
  consumer-defined checks.
- `StatsDMetricsCollector` sends UDP datagrams without delivery acknowledgement. If a socket cannot
  be created or a datagram cannot be sent, the built-in sender returns without escalating the
  failure. Metrics therefore must not be the only record of a consequential business event.
- `LoggingAuditLog` delegates directly to the configured PSR-3 logger. Logger failures can propagate
  to the caller; decide at the composition boundary whether audit failure should fail the use case,
  be retried, or be routed to a durable outbox.
- `NullMetricsCollector` and `NullAuditLog` intentionally discard their inputs.

Expose a health route only through application-owned HTTP handling. Translate the report into the
status code and public shape required by the deployment platform, and bound probe latency so a slow
dependency does not consume the health endpoint indefinitely.

## Privacy and operations

Treat observability payloads as data exported from the use case. Do not place credentials, session
tokens, message bodies, raw personal data, or unrestricted exception context in health messages,
metric tags, or audit metadata. Prefer stable identifiers and low-cardinality metric tags; apply
access control and retention to queryable audit storage.

Health is a current snapshot, metrics are lossy measurements, and audit entries are business facts.
None of them authorizes an operation. Protect any route that exposes these signals, and use the
[Authentication guide](../auth/index.md) for HMAC request signing and replay protection.
